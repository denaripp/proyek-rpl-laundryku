<?php

namespace App\Services\Ai;

/**
 * Contract for the AI provider client.
 *
 * Implementations talk to the remote provider (OpenAI). Tests bind a fake.
 * The service layer never talks to the provider directly — only through
 * this interface — so a provider outage can be caught and degraded
 * gracefully without touching core marketplace flows.
 */
interface AiClientInterface
{
    /**
     * Send a chat completion request with function-calling tools.
     *
     * @param array $messages OpenAI-style message list (system/user/assistant/tool).
     * @param array $tools    Tool definitions (OpenAI function schema).
     *
     * @return array{content: string|null, tool_calls: array, usage: array{prompt_tokens: int, completion_tokens: int}}
     *         tool_calls entries: ['id' => string, 'name' => string, 'arguments' => array]
     *
     * @throws AiProviderException when the provider is down, errors, or returns an invalid payload.
     */
    public function chat(array $messages, array $tools): array;
}