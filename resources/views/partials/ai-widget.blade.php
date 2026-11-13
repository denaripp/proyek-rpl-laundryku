{{-- Floating AI Assistant widget --}}
<button class="lk-ai-widget-btn" id="lkAiToggle" aria-label="Buka asisten AI" title="Tanya AI">
    <i class="bi bi-chat-dots-fill"></i>
</button>

<aside class="lk-ai-panel" id="lkAiPanel" aria-hidden="true">
    <header class="lk-ai-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="bi bi-stars me-1"></i>Asisten LaundryKu</span>
        <button class="btn btn-sm btn-link text-light p-0" id="lkAiClose" aria-label="Tutup">&times;</button>
    </header>
    <div class="lk-ai-messages" id="lkAiMessages">
        <div class="lk-ai-msg bot">
            Halo! Saya asisten LaundryKu. Tanya apa aja, misal: "Cari laundry terdekat di Jakarta" atau "Berapa harga cuci kiloan?".
        </div>
    </div>
    <form class="lk-ai-input" id="lkAiForm">
        <input type="text" class="form-control form-control-sm" id="lkAiInput" placeholder="Tulis pertanyaan..." autocomplete="off" required>
        <button class="btn btn-lk-primary btn-sm" type="submit"><i class="bi bi-send"></i></button>
    </form>
</aside>

@once
@push('scripts')
<script>
(function () {
    const panel = document.getElementById('lkAiPanel');
    const toggle = document.getElementById('lkAiToggle');
    const close = document.getElementById('lkAiClose');
    const form = document.getElementById('lkAiForm');
    const input = document.getElementById('lkAiInput');
    const messages = document.getElementById('lkAiMessages');
    let sessionId = localStorage.getItem('lk_ai_session') || null;
    let sending = false;

    function open() { panel.classList.add('open'); panel.setAttribute('aria-hidden', 'false'); input.focus(); }
    function shut() { panel.classList.remove('open'); panel.setAttribute('aria-hidden', 'true'); }

    toggle.addEventListener('click', () => panel.classList.contains('open') ? shut() : open());
    close.addEventListener('click', shut);

    function appendMsg(role, text) {
        const div = document.createElement('div');
        div.className = 'lk-ai-msg ' + role;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    function appendTyping() {
        const div = document.createElement('div');
        div.className = 'lk-ai-msg bot lk-pulse';
        div.id = 'lkAiTyping';
        div.textContent = 'mengetik...';
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
    }

    form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const text = input.value.trim();
        if (!text || sending) return;
        sending = true;
        input.value = '';
        appendMsg('user', text);
        appendTyping();
        try {
            const apiBase = window.LK?.apiBase || '/api';
            const { data } = await axios.post(apiBase + '/ai/chat', { 
                message: text, 
                conversation_id: sessionId ? parseInt(sessionId, 10) : null 
            });
            document.getElementById('lkAiTyping')?.remove();
            const cid = data.conversation_id || data.session_id;
            if (cid) { 
                sessionId = cid; 
                localStorage.setItem('lk_ai_session', sessionId); 
            }
            appendMsg('bot', data.reply || data.message || 'Maaf, saya tidak mengerti.');
        } catch (err) {
            document.getElementById('lkAiTyping')?.remove();
            const msg = err.response?.status === 401
                ? 'Silakan login dulu untuk bicara dengan asisten.'
                : 'Maaf, asisten sedang sibuk. Coba lagi sebentar.';
            appendMsg('bot', msg);
        } finally {
            sending = false;
            input.focus();
        }
    });
})();
</script>
@endpush
@endonce
