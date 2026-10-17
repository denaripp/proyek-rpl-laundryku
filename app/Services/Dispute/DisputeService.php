<?php

namespace App\Services\Dispute;

use App\Models\AuditLog;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\Refund;
use App\Models\SystemConfiguration;
use App\Models\User;
use App\Services\Refund\RefundService;
use App\Notifications\NotificationTrigger;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Dispute flow: Order -> Complaint -> Dispute Review -> Investigation -> Resolution.
 *
 * SAFETY:
 * - Compensation/refund resulting from a dispute is ALWAYS a human decision
 *   (Platform Admin / Platform Owner). Never automatic. Never AI-driven.
 * - Refunds go through the Refund Service (Tahap 10), never manual off-system.
 * - Every stage is recorded in audit_logs.
 */
class DisputeService
{
    const REASONS = [
        'damaged_item',
        'lost_item',
        'late_delivery',
        'poor_quality',
        'wrong_item',
        'overcharge',
        'other',
    ];

    const OUTCOMES = ['refunded', 'partial_refund', 'compensated', 'reprocessed', 'rejected'];

    protected RefundService $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    /**
     * Open a dispute (Complaint stage). Any party tied to the order may file.
     */
    public function openDispute(Order $order, User $actor, string $reason, string $description, ?array $evidence = null): Dispute
    {
        if (!in_array($reason, self::REASONS, true)) {
            throw new Exception('Invalid dispute reason.');
        }

        $existing = Dispute::where('order_id', $order->id)
            ->whereIn('status', Dispute::OPEN_STATUSES)
            ->exists();
        if ($existing) {
            throw new Exception('An open dispute already exists for this order.');
        }

        return DB::transaction(function () use ($order, $actor, $reason, $description, $evidence) {
            $sla = $this->getSlaConfig();
            $resolutionDays = (int) ($sla['resolution_days'] ?? 7);

            $dispute = Dispute::create([
                'complaint_number' => $this->generateComplaintNumber(),
                'order_id' => $order->id,
                'opened_by' => $actor->id,
                'reason' => $reason,
                'description' => $description,
                'status' => Dispute::STATUS_COMPLAINT_SUBMITTED,
                'evidence' => $evidence,
                'sla_due_at' => now()->addDays($resolutionDays),
                'metadata' => ['opened_at' => now()->toISOString()],
            ]);

            $this->recordEvent($dispute, $actor, 'dispute_opened', null, Dispute::STATUS_COMPLAINT_SUBMITTED, $description);
            $this->logAudit($actor, 'dispute_opened', 'disputes', $dispute->id, null, [
                'order_id' => $order->id,
                'reason' => $reason,
                'status' => Dispute::STATUS_COMPLAINT_SUBMITTED,
            ]);

            // Tahap 15: queued side-effect notification to the customer
            NotificationTrigger::disputeOpened($order->customer, $order, $dispute);

            return $dispute;
        });
    }

    /**
     * Dispute Review stage: authorized reviewer starts reviewing the complaint.
     */
    public function startReview(Dispute $dispute, User $reviewer, ?string $note = null): Dispute
    {
        if ($dispute->status !== Dispute::STATUS_COMPLAINT_SUBMITTED) {
            throw new Exception('Dispute review can only start from COMPLAINT_SUBMITTED status.');
        }

        return DB::transaction(function () use ($dispute, $reviewer, $note) {
            $old = $dispute->status;
            $dispute->status = Dispute::STATUS_UNDER_REVIEW;
            $dispute->assigned_to = $reviewer->id;
            $dispute->save();

            $this->recordEvent($dispute, $reviewer, 'dispute_review_started', $old, $dispute->status, $note);
            $this->logAudit($reviewer, 'dispute_review_started', 'disputes', $dispute->id, ['status' => $old], ['status' => $dispute->status]);

            return $dispute;
        });
    }

    /**
     * Ask the complainant for additional information (keeps current stage).
     */
    public function requestMoreInfo(Dispute $dispute, User $reviewer, string $question): Dispute
    {
        if (!$dispute->isOpen()) {
            throw new Exception('Cannot request info on a closed dispute.');
        }

        return DB::transaction(function () use ($dispute, $reviewer, $question) {
            $this->recordEvent($dispute, $reviewer, 'info_requested', $dispute->status, $dispute->status, $question);
            $this->logAudit($reviewer, 'dispute_info_requested', 'disputes', $dispute->id, null, ['question' => $question]);

            return $dispute;
        });
    }

    /**
     * Investigation stage: reviewer moves dispute into investigation.
     */
    public function startInvestigation(Dispute $dispute, User $reviewer, ?string $note = null): Dispute
    {
        if (!in_array($dispute->status, [Dispute::STATUS_COMPLAINT_SUBMITTED, Dispute::STATUS_UNDER_REVIEW], true)) {
            throw new Exception('Investigation can only start from COMPLAINT_SUBMITTED or UNDER_REVIEW status.');
        }

        return DB::transaction(function () use ($dispute, $reviewer, $note) {
            $old = $dispute->status;
            $dispute->status = Dispute::STATUS_INVESTIGATION;
            $dispute->assigned_to = $reviewer->id;
            $dispute->save();

            $this->recordEvent($dispute, $reviewer, 'investigation_started', $old, $dispute->status, $note);
            $this->logAudit($reviewer, 'dispute_investigation_started', 'disputes', $dispute->id, ['status' => $old], ['status' => $dispute->status]);

            return $dispute;
        });
    }

    /**
     * Resolution stage. HUMAN DECISION ONLY.
     *
     * If outcome requires refund (refunded / partial_refund), the refund is
     * created via RefundService (Tahap 10) with status 'requested' — it still
     * needs explicit approval + process by an authorized role. NOTHING here is
     * automatic, and AI Assistant must never call this on its own.
     */
    public function resolve(
        Dispute $dispute,
        User $resolver,
        string $outcome,
        string $resolutionNote,
        ?float $refundAmount = null,
        ?string $refundMethod = null
    ): Dispute {
        if (!$dispute->isOpen()) {
            throw new Exception('Only open disputes can be resolved.');
        }

        if (!in_array($outcome, self::OUTCOMES, true)) {
            throw new Exception('Invalid resolution outcome.');
        }

        $policy = $this->getCompensationPolicy();
        if (empty($policy['requires_human_approval'])) {
            throw new Exception('COMPENSATION_POLICY config is missing or invalid; compensation requires human approval.');
        }

        return DB::transaction(function () use ($dispute, $resolver, $outcome, $resolutionNote, $refundAmount, $refundMethod, $policy) {
            $old = $dispute->status;

            $dispute->status = Dispute::STATUS_RESOLVED;
            $dispute->resolution_outcome = $outcome;
            $dispute->resolution_note = $resolutionNote;
            $dispute->resolved_by = $resolver->id;
            $dispute->resolved_at = now();
            $dispute->save();

            $this->recordEvent($dispute, $resolver, 'dispute_resolved', $old, $dispute->status, $resolutionNote);
            $this->logAudit($resolver, 'dispute_resolved', 'disputes', $dispute->id, ['status' => $old], [
                'status' => $dispute->status,
                'outcome' => $outcome,
            ]);

            // Compensation via refund: route through Refund Service (Tahap 10).
            if (in_array($outcome, ['refunded', 'partial_refund'], true)) {
                $this->createRefundFromDispute($dispute, $resolver, $outcome, $resolutionNote, $refundAmount, $refundMethod);
            }

            // Tahap 15: queued side-effect notification
            NotificationTrigger::disputeResolved($dispute->order->customer, $dispute->order, $dispute, $outcome);

            return $dispute;
        });
    }

    /**
     * Reject the dispute (no compensation).
     */
    public function reject(Dispute $dispute, User $reviewer, string $note): Dispute
    {
        if (!$dispute->isOpen()) {
            throw new Exception('Only open disputes can be rejected.');
        }

        return DB::transaction(function () use ($dispute, $reviewer, $note) {
            $old = $dispute->status;
            $dispute->status = Dispute::STATUS_REJECTED;
            $dispute->resolution_note = $note;
            $dispute->resolved_by = $reviewer->id;
            $dispute->resolved_at = now();
            $dispute->save();

            $this->recordEvent($dispute, $reviewer, 'dispute_rejected', $old, $dispute->status, $note);
            $this->logAudit($reviewer, 'dispute_rejected', 'disputes', $dispute->id, ['status' => $old], ['status' => $dispute->status]);

            return $dispute;
        });
    }

    /**
     * Create a refund REQUEST via RefundService. The refund stays in
     * 'requested' status until explicitly approved & processed by an
     * authorized human (RefundService::processRefund). Never auto-executed.
     */
    protected function createRefundFromDispute(
        Dispute $dispute,
        User $resolver,
        string $outcome,
        string $resolutionNote,
        ?float $refundAmount,
        ?string $refundMethod
    ): Refund {
        $order = $dispute->order;
        $payment = $order->payment;

        if (!$payment) {
            throw new Exception('Cannot refund: order has no payment record.');
        }

        $amount = $refundAmount ?? ($outcome === 'refunded' ? (float) $payment->amount : null);
        if ($amount === null) {
            throw new Exception('Refund amount is required for partial_refund resolution.');
        }

        $refund = $this->refundService->requestRefund(
            $order,
            $payment,
            $amount,
            'Dispute ' . $dispute->complaint_number . ': ' . $resolutionNote,
            $refundMethod ?? 'manual',
            $resolver
        );

        $dispute->refund_id = $refund->id;
        $dispute->save();

        $this->logAudit($resolver, 'dispute_refund_requested', 'disputes', $dispute->id, null, [
            'refund_id' => $refund->id,
            'amount' => $amount,
            'note' => 'Refund created as REQUESTED; explicit approval still required.',
        ]);

        return $refund;
    }

    // ========== CONFIG (placeholders, not hardcoded) ==========

    public function getCompensationPolicy(): array
    {
        $config = SystemConfiguration::where('key', 'COMPENSATION_POLICY')->first();
        return $config ? (array) $config->value : [];
    }

    public function getSlaConfig(): array
    {
        $config = SystemConfiguration::where('key', 'DISPUTE_SLA')->first();
        return $config ? (array) $config->value : [];
    }

    // ========== INTERNALS ==========

    protected function generateComplaintNumber(): string
    {
        return 'DSP-' . date('Ymd') . '-' . strtoupper(Str::random(8));
    }

    protected function recordEvent(Dispute $dispute, User $actor, string $action, ?string $from, ?string $to, ?string $note = null, ?array $evidence = null): void
    {
        $dispute->events()->create([
            'actor_id' => $actor->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'evidence' => $evidence,
            'created_at' => now(),
        ]);
    }

    protected function logAudit(User $actor, string $action, string $resourceType, int $resourceId, ?array $oldValue, ?array $newValue): void
    {
        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'before_value' => $oldValue,
            'after_value' => $newValue,
            'ip_address' => request()->ip() ?? null,
            'user_agent' => request()->userAgent() ?? null,
            'created_at' => now(),
        ]);
    }
}