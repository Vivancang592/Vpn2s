(() => {
    const root = document.getElementById('vc-ai-chat');
    if (!root) return;

    const launcher = root.querySelector('.vc-ai-chat-launcher');
    const panel = root.querySelector('.vc-ai-chat-panel');
    const closeButton = root.querySelector('.vc-ai-chat-close');
    const form = root.querySelector('.vc-ai-chat-form');
    const input = root.querySelector('#vc-ai-chat-input');
    const list = root.querySelector('.vc-ai-chat-messages');
    const status = root.querySelector('.vc-ai-chat-status');
    const fileInput = root.querySelector('.vc-ai-chat-file');
    const attachButton = root.querySelector('.vc-ai-chat-attach');
    const attachmentPreview = root.querySelector('.vc-ai-chat-attachment');
    const sendButton = root.querySelector('.vc-ai-chat-send');
    const contactForm = root.querySelector('.vc-ai-chat-contact');
    const contactEmail = root.querySelector('#vc-ai-chat-email');
    const defaultInputPlaceholder = input.placeholder;
    const scriptUrl = document.currentScript ? new URL(document.currentScript.src, window.location.href) : new URL(window.location.href);
    const basePath = scriptUrl.pathname.replace(/\/assets\/js\/ai-customer-service-chat\.js$/, '').replace(/\/+$/, '');
    let csrfToken = '';
    let open = false;
    let sending = false;
    let pendingReplyTimer = 0;
    let awaitingReplyAfterId = '';
    let selectedImageUrl = '';
    let lastKnownMessageId = 0;
    let conversationState = 'ai_active';
    let typingIndicatorSource = '';
    let typingIndicatorLabel = '';
    let typingIndicatorElement = null;
    let typingHideTimer = 0;
    let typingPollInProgress = false;
    let replyTimedOut = false;
    let bootstrapReady = false;
    let notificationPermissionRequested = false;
    let replyToneContext = null;
    let renderedMessagesSignature = '';
    let customerTyping = false;
    let customerTypingStopTimer = 0;
    let customerTypingHeartbeatTimer = 0;
    let lastTypingError = '';
    let pendingMessageCount = 0;
    let maxPendingMessages = 5;
    const pendingReplyStorageKey = 'vc-ai-chat-pending-reply';
    const pendingReplyTimeoutMs = 2 * 60 * 1000;

    function pendingMessageLimitNotice() {
        return `Bạn đã gửi tối đa ${maxPendingMessages} tin nhắn đang chờ phản hồi. Vui lòng đợi Nhân viên hoặc Kỹ thuật viên trả lời trước khi gửi tiếp.`;
    }

    function updateComposerControls() {
        const disabled = !bootstrapReady || sending || pendingMessageCount >= maxPendingMessages;
        input.disabled = disabled;
        sendButton.disabled = disabled;
        attachButton.disabled = disabled;
        input.placeholder = pendingMessageCount >= maxPendingMessages
            ? `Đang chờ phản hồi cho ${maxPendingMessages} tin nhắn...`
            : defaultInputPlaceholder;
        sendButton.title = pendingMessageCount >= maxPendingMessages ? pendingMessageLimitNotice() : '';
    }

    function updatePendingMessageCount(count, maximum) {
        const parsedCount = Number(count);
        const parsedMaximum = Number(maximum);
        if (Number.isInteger(parsedCount) && parsedCount >= 0) pendingMessageCount = parsedCount;
        if (Number.isInteger(parsedMaximum) && parsedMaximum > 0) maxPendingMessages = parsedMaximum;
        updateComposerControls();
        if (pendingMessageCount >= maxPendingMessages) {
            contactForm.hidden = true;
            setReplyStatus(pendingMessageLimitNotice());
        } else if (status.textContent === pendingMessageLimitNotice()) {
            setReplyStatus('');
        }
    }

    function syncChatViewport() {
        const viewportHeight = window.visualViewport?.height || window.innerHeight;
        root.style.setProperty('--vc-ai-chat-viewport-height', Math.round(viewportHeight) + 'px');
    }

    function showBusyMessage() {
        const pending = readPendingReply();
        if (!isAiResponsible() || !pending || Date.now() - Number(pending.startedAt) < pendingReplyTimeoutMs) return;
        pendingReplyTimer = 0;
        replyTimedOut = true;
        clearTypingIndicator();
        if (pendingMessageCount >= maxPendingMessages) {
            status.textContent = pendingMessageLimitNotice();
            contactForm.hidden = true;
            return;
        }
        status.textContent = 'Hiện tại tất cả tư vấn viên đều bận. Vui lòng nhắn lại sau ít phút hoặc để lại email, chúng tôi sẽ liên hệ lại.';
        contactForm.hidden = false;
    }

    function armPendingReplyTimer(startedAt = Date.now()) {
        if (pendingReplyTimer || replyTimedOut || !isAiResponsible()) return;
        const remaining = Math.max(0, pendingReplyTimeoutMs - (Date.now() - startedAt));
        pendingReplyTimer = window.setTimeout(() => {
            pendingReplyTimer = 0;
            const pending = readPendingReply();
            if (!pending || !isAiResponsible()) return;
            const elapsed = Date.now() - Number(pending.startedAt);
            if (elapsed < pendingReplyTimeoutMs) {
                armPendingReplyTimer(Number(pending.startedAt));
                return;
            }
            showBusyMessage();
        }, remaining);
    }

    function isAiResponsible() {
        return conversationState === 'ai_active' || conversationState === 'error';
    }

    function clearPendingReply() {
        if (pendingReplyTimer) {
            window.clearTimeout(pendingReplyTimer);
            pendingReplyTimer = 0;
        }
        replyTimedOut = false;
        awaitingReplyAfterId = '';
        status.textContent = '';
        contactForm.hidden = true;
    }

    function setReplyStatus(text) {
        const typingIndicatorVisible = typingIndicatorSource !== ''
            && typingIndicatorElement !== null
            && !typingIndicatorElement.classList.contains('is-hidden');
        status.textContent = typingIndicatorVisible && text === typingIndicatorLabel ? '' : text;
    }

    function updateContactLabel() {
        const label = contactForm.querySelector('label');
        if (!label) return;
        label.textContent = 'Email để nhân viên liên hệ';
    }

    function updateConversationState(state) {
        if (!['ai_active', 'ai_paused', 'staff_active', 'waiting', 'error'].includes(state)) return;
        const wasTimedOut = replyTimedOut || !contactForm.hidden;
        conversationState = state;
        if (!isAiResponsible()) {
            if (pendingReplyTimer) {
                window.clearTimeout(pendingReplyTimer);
                pendingReplyTimer = 0;
            }
            replyTimedOut = false;
            contactForm.hidden = true;
            if (wasTimedOut) status.textContent = '';
            clearTypingIndicator('ai');
        }
    }

    function pendingStaffStatus(state) {
        if (state === 'waiting') return 'Yêu cầu của bạn đang chờ kỹ thuật viên tiếp nhận...';
        if (state === 'staff_active') return 'Kỹ thuật viên đã tiếp nhận và sẽ phản hồi tại đây...';
        if (state === 'ai_paused') return 'Nhân viên đang tạm dừng trả lời tự động.';
        return 'Tin nhắn của bạn đang chờ được xử lý...';
    }

    function readPendingReply() {
        try {
            const value = JSON.parse(window.sessionStorage.getItem(pendingReplyStorageKey) || 'null');
            if (!value || typeof value !== 'object' || !Number.isFinite(Number(value.startedAt))
                || Date.now() - Number(value.startedAt) > 24 * 60 * 60 * 1000) {
                window.sessionStorage.removeItem(pendingReplyStorageKey);
                return null;
            }
            return value;
        } catch {
            return null;
        }
    }

    function savePendingReply(value) {
        try {
            if (value) {
                window.sessionStorage.setItem(pendingReplyStorageKey, JSON.stringify(value));
            } else {
                window.sessionStorage.removeItem(pendingReplyStorageKey);
            }
        } catch {
            // Storage can be unavailable in private browsing; polling still works on this page.
        }
    }

    function newestMessageId(messages) {
        return Number(messages[messages.length - 1]?.id || 0);
    }

    function findPendingReply(messages, pending) {
        if (!pending) return null;
        const baseline = Number(pending.afterId || 0);
        const pendingCustomerId = Number(pending.customerId || 0);
        const customer = [...messages].reverse().find((message) =>
            message.kind === 'customer'
            && Number(message.id) > baseline
            && (!pendingCustomerId || Number(message.id) >= pendingCustomerId)
        );
        if (!customer) return null;
        const customerId = Number(customer.id);
        const reply = messages.find((message) =>
            (message.kind === 'ai' || message.kind === 'staff')
            && Number(message.id) > customerId
        );
        return { customer, reply };
    }

    function unlockReplyTone() {
        if (!replyToneContext) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (!AudioContextClass) return;
            try {
                replyToneContext = new AudioContextClass();
            } catch {
                return;
            }
        }
        if (replyToneContext.state === 'suspended') {
            replyToneContext.resume().catch(() => {});
        }
    }

    function playReplyTone() {
        if (!replyToneContext || replyToneContext.state !== 'running') return;
        const now = replyToneContext.currentTime;
        [880, 1174].forEach((frequency, index) => {
            const oscillator = replyToneContext.createOscillator();
            const gain = replyToneContext.createGain();
            const start = now + index * 0.16;
            oscillator.type = 'sine';
            oscillator.frequency.value = frequency;
            gain.gain.setValueAtTime(0.0001, start);
            gain.gain.exponentialRampToValueAtTime(0.12, start + 0.025);
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.13);
            oscillator.connect(gain);
            gain.connect(replyToneContext.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.14);
        });
    }

    function requestNotificationPermission() {
        if (notificationPermissionRequested || !('Notification' in window) || Notification.permission !== 'default') return;
        notificationPermissionRequested = true;
        try {
            Notification.requestPermission().catch(() => {});
        } catch {
            notificationPermissionRequested = false;
        }
    }

    function notifyNewReply() {
        playReplyTone();
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                new Notification('VPN2S', { body: 'Bạn có phản hồi mới trong chat hỗ trợ.' });
            } catch {
                // Browser notification may be unavailable while the page is inactive.
            }
        }
        if (!open) setOpen(true, false);
    }

    function buildTypingBadge(labelText = 'Đang soạn tin') {
        const typing = document.createElement('div');
        typing.className = 'vc-ai-chat-typing';
        typing.setAttribute('role', 'status');
        const dotWrap = document.createElement('span');
        dotWrap.className = 'vc-ai-chat-typing-dots';
        dotWrap.setAttribute('aria-hidden', 'true');
        for (let index = 0; index < 3; index++) {
            const dot = document.createElement('span');
            dot.className = 'vc-ai-chat-typing-dot';
            dot.textContent = '.';
            dotWrap.appendChild(dot);
        }
        const label = document.createElement('span');
        label.className = 'vc-ai-chat-typing-label';
        label.textContent = labelText;
        typing.append(label, dotWrap);
        return typing;
    }

    function renderWelcome() {
        const welcome = document.createElement('div');
        welcome.className = 'vc-ai-chat-welcome';
        const greeting = document.createElement('strong');
        greeting.textContent = 'Chào bạn! VPN2S có thể tư vấn gói dịch vụ, hướng dẫn cài đặt và hỗ trợ đơn hàng. Bạn cần hỗ trợ gì?';
        welcome.appendChild(greeting);
        const options = document.createElement('div');
        options.className = 'vc-ai-chat-options';
        [
            'Tư vấn gói VPN phù hợp',
            'Hướng dẫn cài đặt',
            'Hỗ trợ đơn hàng, thanh toán',
            'Gặp kỹ thuật viên'
        ].forEach((label) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = label;
            button.addEventListener('click', () => {
                input.value = label;
                form.requestSubmit();
            });
            options.appendChild(button);
        });
        welcome.appendChild(options);
        list.appendChild(welcome);
    }

    function appendMessage(message) {
        if (!message || typeof message.text !== 'string') return;
        const kind = ['customer', 'ai', 'staff'].includes(message.kind) ? message.kind : 'staff';
        const card = document.createElement('article');
        card.className = 'vc-ai-chat-message ' + kind;
        const sender = document.createElement('strong');
        sender.className = 'vc-ai-chat-sender';
        const senderName = kind === 'customer' ? 'Bạn' : kind === 'staff' ? 'Kỹ thuật viên' : 'Nhân viên';
        sender.textContent = senderName;
        const meta = document.createElement('span');
        meta.className = 'vc-ai-chat-message-meta';
        meta.textContent = message.time || '';
        sender.appendChild(meta);
        card.appendChild(sender);
        const bubble = document.createElement('div');
        bubble.className = 'vc-ai-chat-message-bubble';
        const text = document.createElement('div');
        text.className = 'vc-ai-chat-message-text';
        text.textContent = message.text;
        bubble.appendChild(text);
        if (typeof message.image_url === 'string' && message.image_url !== '') {
            const image = document.createElement('img');
            image.className = 'vc-ai-chat-message-image';
            image.src = basePath + message.image_url;
            image.alt = 'Ảnh khách hàng đính kèm';
            image.loading = 'lazy';
            bubble.appendChild(image);
        }
        card.appendChild(bubble);
        list.appendChild(card);
    }

    function render(messages) {
        const items = Array.isArray(messages) ? messages : [];
        const signature = JSON.stringify(items);
        if (signature !== renderedMessagesSignature) {
            renderedMessagesSignature = signature;
            list.replaceChildren();
            if (items.length === 0) {
                renderWelcome();
            } else {
                items.forEach(appendMessage);
            }
            ensureTypingIndicator();
            if (items.length > 0) list.scrollTop = list.scrollHeight;
            if (typingIndicatorSource) renderTyping(typingIndicatorSource, typingIndicatorLabel);
        }
    }

    function ensureTypingIndicator() {
        if (!typingIndicatorElement) {
            typingIndicatorElement = buildTypingBadge();
            typingIndicatorElement.classList.add('is-hidden');
            typingIndicatorElement.setAttribute('aria-hidden', 'true');
        }
        if (typingIndicatorElement.parentNode !== list) list.appendChild(typingIndicatorElement);
    }

    function renderTyping(source, label) {
        ensureTypingIndicator();
        if (typingIndicatorSource !== source) {
            clearTypingIndicator();
        }
        typingIndicatorSource = source;
        typingIndicatorLabel = label;
        typingIndicatorElement.classList.remove('is-hiding', 'is-hidden');
        typingIndicatorElement.setAttribute('aria-hidden', 'false');
        typingIndicatorElement.querySelector('.vc-ai-chat-typing-label').textContent = label;
        if (status.textContent === label) status.textContent = '';
    }

    function clearTypingIndicator(source = '') {
        if (source && typingIndicatorSource !== source) return;
        if (typingHideTimer) {
            window.clearTimeout(typingHideTimer);
            typingHideTimer = 0;
        }
        if (typingIndicatorElement) {
            typingIndicatorElement.classList.remove('is-hiding');
            typingIndicatorElement.classList.add('is-hidden');
            typingIndicatorElement.setAttribute('aria-hidden', 'true');
        }
        typingIndicatorSource = '';
        typingIndicatorLabel = '';
    }

    function scheduleTypingIndicatorHide(source, delay = 900) {
        if (typingIndicatorSource !== source || typingHideTimer) return;
        typingIndicatorElement?.classList.add('is-hiding');
        typingHideTimer = window.setTimeout(() => {
            typingHideTimer = window.setTimeout(() => {
                typingHideTimer = 0;
                clearTypingIndicator(source);
            }, 180);
        }, delay);
    }

    async function sendCustomerTyping(typing, heartbeat = false) {
        if (!csrfToken || (customerTyping === typing && !heartbeat)) return;
        customerTyping = typing;
        try {
            await request('/api/ai-customer-service/typing', {
                method: 'POST',
                body: JSON.stringify({ typing, csrf_token: csrfToken })
            });
            lastTypingError = '';
        } catch (error) {
            customerTyping = false;
            if (customerTypingHeartbeatTimer) {
                window.clearInterval(customerTypingHeartbeatTimer);
                customerTypingHeartbeatTimer = 0;
            }
            const message = error instanceof Error ? error.message : 'Không thể đồng bộ trạng thái đang soạn tin.';
            if (message !== lastTypingError) {
                status.textContent = message;
                lastTypingError = message;
            }
        }
    }

    function stopCustomerTyping() {
        if (customerTypingStopTimer) {
            window.clearTimeout(customerTypingStopTimer);
            customerTypingStopTimer = 0;
        }
        if (customerTypingHeartbeatTimer) {
            window.clearInterval(customerTypingHeartbeatTimer);
            customerTypingHeartbeatTimer = 0;
        }
        void sendCustomerTyping(false);
    }

    async function refreshStaffTyping() {
        if (!open || !bootstrapReady || typingPollInProgress) return;
        typingPollInProgress = true;
        try {
            const result = await request('/api/ai-customer-service/typing');
            if (result.staff_typing === true) {
                if (typingHideTimer) {
                    window.clearTimeout(typingHideTimer);
                    typingHideTimer = 0;
                }
                renderTyping('staff', 'Kỹ thuật viên đang soạn tin');
            } else {
                scheduleTypingIndicatorHide('staff');
            }
        } catch (error) {
            const message = error instanceof Error ? error.message : 'Không thể đồng bộ trạng thái đang soạn tin.';
            if (message !== lastTypingError && document.visibilityState === 'visible') {
                status.textContent = message;
                lastTypingError = message;
            }
        } finally {
            typingPollInProgress = false;
        }
    }

    function showAttachment(file) {
        if (selectedImageUrl) URL.revokeObjectURL(selectedImageUrl);
        selectedImageUrl = URL.createObjectURL(file);
        attachmentPreview.replaceChildren();
        const image = document.createElement('img');
        image.src = selectedImageUrl;
        image.alt = 'Ảnh sắp gửi';
        const remove = document.createElement('button');
        remove.type = 'button';
        remove.textContent = '×';
        remove.setAttribute('aria-label', 'Bỏ ảnh đính kèm');
        remove.addEventListener('click', clearAttachment);
        attachmentPreview.append(image, remove);
        attachmentPreview.hidden = false;
    }

    function clearAttachment() {
        if (selectedImageUrl) URL.revokeObjectURL(selectedImageUrl);
        selectedImageUrl = '';
        fileInput.value = '';
        attachmentPreview.replaceChildren();
        attachmentPreview.hidden = true;
    }

    async function request(url, options = {}) {
        const response = await fetch(basePath + url, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', ...(options.body && !(options.body instanceof FormData) ? { 'Content-Type': 'application/json' } : {}) },
            ...options
        });
        let result;
        try {
            result = await response.json();
        } catch {
            throw new Error('Không thể kết nối dịch vụ hỗ trợ.');
        }
        if (!response.ok || result.ok !== true) {
            const error = new Error(typeof result.message === 'string' ? result.message : 'Yêu cầu chưa được xử lý.');
            error.status = response.status;
            error.payload = result;
            throw error;
        }
        return result;
    }

    function messageId() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        if (!window.crypto || typeof window.crypto.getRandomValues !== 'function') {
            throw new Error('Trình duyệt không hỗ trợ mã hóa an toàn cho phiên chat.');
        }
        const bytes = new Uint8Array(16);
        window.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('').replace(/^(.{8})(.{4})(.{4})(.{4})(.{12})$/, '$1-$2-$3-$4-$5');
    }

    function setOpen(value, focusInput = true) {
        open = value;
        panel.hidden = !value;
        launcher.setAttribute('aria-expanded', String(value));
        if (value && focusInput) input.focus();
    }

    async function sendMessage(message, image = null) {
        if ((!message && !image) || sending || !csrfToken) return false;
        if (pendingMessageCount >= maxPendingMessages) {
            setReplyStatus(pendingMessageLimitNotice());
            return false;
        }
        clearPendingReply();
        const pending = { afterId: lastKnownMessageId, startedAt: Date.now(), customerId: 0 };
        savePendingReply(pending);
        sending = true;
        updateComposerControls();
        contactForm.hidden = true;
        setReplyStatus('');

        if (list.querySelector('.vc-ai-chat-welcome')) list.replaceChildren();
        appendMessage({ kind: 'customer', text: message || 'Đã gửi một ảnh đính kèm.', time: new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit', hour12: false }) });
        if (image) {
            const imageCard = list.lastElementChild;
            const preview = document.createElement('img');
            preview.className = 'vc-ai-chat-message-image';
            preview.src = selectedImageUrl;
            preview.alt = 'Ảnh đính kèm của bạn';
            imageCard.appendChild(preview);
        }
        stopCustomerTyping();
        if (isAiResponsible()) {
            renderTyping('ai', 'Nhân viên đang xử lý tin nhắn...');
        } else {
            clearTypingIndicator('ai');
        }
        const outgoingMessageId = messageId();

        try {
            let body;
            if (image) {
                body = new FormData();
                body.append('message', message);
                body.append('message_id', outgoingMessageId);
                body.append('csrf_token', csrfToken);
                body.append('image', image);
            } else {
                body = JSON.stringify({ message, message_id: outgoingMessageId, csrf_token: csrfToken });
            }
            const result = await request('/api/ai-customer-service/message', {
                method: 'POST',
                body
            });
            updateConversationState(result.state);
            input.value = '';
            clearAttachment();
            render(Array.isArray(result.messages) ? result.messages : []);
            const messages = Array.isArray(result.messages) ? result.messages : [];
            const lastCustomerIndex = messages.map((item) => item.kind).lastIndexOf('customer');
            const hasReply = messages.slice(lastCustomerIndex + 1).some((item) => item.kind === 'ai' || item.kind === 'staff');
            const customerMessage = messages[lastCustomerIndex];
            if (customerMessage) {
                pending.customerId = Number(customerMessage.id || 0);
                savePendingReply(pending);
            }
            if (!open && hasReply) notifyNewReply();
            if (hasReply) {
                clearPendingReply();
                savePendingReply(null);
                clearTypingIndicator();
            } else if (result.state === 'staff_active' || result.state === 'waiting') {
                setReplyStatus(pendingStaffStatus(result.state));
                awaitingReplyAfterId = customerMessage?.id || '';
                contactForm.hidden = true;
                clearTypingIndicator('ai');
            } else if (typeof result.reply === 'string' && result.reply !== '') {
                if (isAiResponsible() && customerMessage) {
                    pending.customerId = Number(customerMessage.id || 0);
                    savePendingReply(pending);
                    awaitingReplyAfterId = String(customerMessage.id);
                    setReplyStatus('Nhân viên đang xử lý tin nhắn...');
                    armPendingReplyTimer(pending.startedAt);
                } else {
                    clearPendingReply();
                    savePendingReply(null);
                }
            } else if (isAiResponsible() && customerMessage) {
                pending.customerId = Number(customerMessage.id || 0);
                savePendingReply(pending);
                awaitingReplyAfterId = String(customerMessage.id);
                setReplyStatus('Nhân viên đang xử lý tin nhắn...');
                armPendingReplyTimer(pending.startedAt);
            } else {
                clearPendingReply();
                savePendingReply(null);
                clearTypingIndicator('ai');
            }
            updatePendingMessageCount(result.pending_messages, result.max_pending_messages);
            lastKnownMessageId = Math.max(lastKnownMessageId, newestMessageId(messages));
            return true;
        } catch (error) {
            if (error instanceof Error && error.status === 409 && error.payload) {
                updatePendingMessageCount(error.payload.pending_messages, error.payload.max_pending_messages);
            }
            const messageText = error instanceof Error ? error.message : 'Không thể gửi tin nhắn.';
            setReplyStatus(messageText);
            clearTypingIndicator('ai');
            return false;
        } finally {
            sending = false;
            updateComposerControls();
            input.focus();
        }
    }

    launcher.addEventListener('click', () => {
        unlockReplyTone();
        if (open) stopCustomerTyping();
        setOpen(!open);
    });
    closeButton.addEventListener('click', () => {
        stopCustomerTyping();
        setOpen(false);
        launcher.focus();
    });
    document.addEventListener('pointerdown', unlockReplyTone, { once: true });
    document.addEventListener('keydown', unlockReplyTone, { once: true });
    syncChatViewport();
    window.addEventListener('resize', syncChatViewport, { passive: true });
    window.visualViewport?.addEventListener('resize', syncChatViewport, { passive: true });
    window.visualViewport?.addEventListener('scroll', syncChatViewport, { passive: true });
    attachButton.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            status.textContent = 'Chỉ chọn ảnh JPEG, PNG, WebP hoặc GIF có dung lượng tối đa 5 MB.';
            clearAttachment();
            return;
        }
        status.textContent = '';
        showAttachment(file);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        unlockReplyTone();
        requestNotificationPermission();
        await sendMessage(input.value.trim(), fileInput.files?.[0] || null);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();
            form.requestSubmit();
        }
    });
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(input.scrollHeight, 140) + 'px';
        if (input.value.trim() && !sending) {
            void sendCustomerTyping(true);
            if (!customerTypingHeartbeatTimer) {
                customerTypingHeartbeatTimer = window.setInterval(() => {
                    if (customerTyping) void sendCustomerTyping(true, true);
                }, 1200);
            }
            if (customerTypingStopTimer) window.clearTimeout(customerTypingStopTimer);
            customerTypingStopTimer = window.setTimeout(stopCustomerTyping, 1600);
        } else {
            stopCustomerTyping();
        }
    });
    input.addEventListener('blur', stopCustomerTyping);

    contactForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!isAiResponsible()) {
            contactForm.hidden = true;
            return;
        }
        const email = contactEmail.value.trim();
        if (!contactEmail.checkValidity() || sending) {
            contactEmail.reportValidity();
            return;
        }
        contactForm.hidden = true;
        status.textContent = 'Đang gửi email để nhân viên liên hệ...';
        const sent = await sendMessage('Tôi để lại email ' + email + ' để được liên hệ lại.');
        if (sent) contactEmail.value = '';
    });

    render([]);
    request('/api/ai-customer-service/session')
        .then((result) => {
            if (!result.enabled) {
                root.remove();
                return;
            }
            csrfToken = result.csrf_token;
            updateConversationState(result.state);
            const initialMessages = Array.isArray(result.messages) ? result.messages : [];
            render(initialMessages);
            lastKnownMessageId = newestMessageId(initialMessages);
            const pending = readPendingReply();
            const pendingStatus = findPendingReply(initialMessages, pending);
            if (pendingStatus?.reply) {
                savePendingReply(null);
                notifyNewReply();
            } else if (pendingStatus?.customer) {
                pending.customerId = Number(pendingStatus.customer.id);
                savePendingReply(pending);
                awaitingReplyAfterId = String(pending.customer.id);
                if (isAiResponsible()) {
                    if (!replyTimedOut) setReplyStatus('Nhân viên đang xử lý tin nhắn...');
                    armPendingReplyTimer(pending.startedAt);
                } else {
                    setReplyStatus(pendingStaffStatus(result.state));
                }
            }
            const latestMessage = initialMessages[initialMessages.length - 1];
            const isWaitingForStaff = (result.state === 'waiting' || result.state === 'staff_active')
                && latestMessage?.kind === 'customer'
                && !pendingStatus?.reply;
            if (isWaitingForStaff) {
                if (!awaitingReplyAfterId) {
                    awaitingReplyAfterId = String(latestMessage.id);
                    const latestCustomerIndex = initialMessages.length - 1;
                    const baseline = latestCustomerIndex > 0 ? Number(initialMessages[latestCustomerIndex - 1].id) : 0;
                    const pendingReply = { afterId: baseline, customerId: Number(latestMessage.id), startedAt: Date.now() };
                    savePendingReply(pendingReply);
                }
                setReplyStatus(pendingStaffStatus(result.state));
                contactForm.hidden = true;
                clearTypingIndicator('ai');
            } else if (!pendingStatus?.customer && !pendingStatus?.reply) {
                clearPendingReply();
                savePendingReply(null);
                clearTypingIndicator('ai');
            }
            bootstrapReady = true;
            updatePendingMessageCount(result.pending_messages, result.max_pending_messages);
        })
        .catch((error) => {
            status.textContent = error instanceof Error ? error.message : 'Không thể khởi tạo chat hỗ trợ.';
            updateComposerControls();
        });

    window.setInterval(async () => {
        if (!bootstrapReady || sending || (!open && !readPendingReply() && !awaitingReplyAfterId)) return;
        try {
            const result = await request('/api/ai-customer-service/messages');
            updateConversationState(result.state);
            const messages = Array.isArray(result.messages) ? result.messages : [];
            const currentNewestId = newestMessageId(messages);
            const pending = readPendingReply();
            const pendingStatus = findPendingReply(messages, pending);
            const newReplyWhileWaiting = Boolean(pendingStatus?.reply)
                && Number(pendingStatus.reply.id) > lastKnownMessageId;
            render(messages);
            if (newReplyWhileWaiting) {
                savePendingReply(null);
                clearPendingReply();
                contactForm.hidden = true;
                notifyNewReply();
            }
            if (pendingStatus?.customer && !pendingStatus.reply) {
                pending.customerId = Number(pendingStatus.customer.id);
                savePendingReply(pending);
                awaitingReplyAfterId = String(pendingStatus.customer.id);
                if (isAiResponsible()) {
                    if (!replyTimedOut) setReplyStatus('Nhân viên đang xử lý tin nhắn...');
                    armPendingReplyTimer(pending.startedAt);
                } else {
                    setReplyStatus(pendingStaffStatus(result.state));
                    contactForm.hidden = true;
                }
            } else if (pendingStatus?.reply && !newReplyWhileWaiting) {
                savePendingReply(null);
                clearPendingReply();
                contactForm.hidden = true;
            }
            if (messages.length > 0) lastKnownMessageId = Math.max(lastKnownMessageId, currentNewestId);
            const latestMessage = messages[messages.length - 1];
            const isWaitingForStaff = (result.state === 'waiting' || result.state === 'staff_active')
                && latestMessage?.kind === 'customer'
                && !pendingStatus?.reply;
            if (isWaitingForStaff) {
                if (!awaitingReplyAfterId || Number(awaitingReplyAfterId) !== Number(latestMessage.id)) {
                    awaitingReplyAfterId = String(latestMessage.id);
                    const latestCustomerIndex = messages.length - 1;
                    const baseline = latestCustomerIndex > 0 ? Number(messages[latestCustomerIndex - 1].id) : 0;
                    const newPending = { afterId: baseline, customerId: Number(latestMessage.id), startedAt: Date.now() };
                    savePendingReply(newPending);
                }
                setReplyStatus(pendingStaffStatus(result.state));
                contactForm.hidden = true;
                clearTypingIndicator('ai');
            } else if (pendingStatus?.reply || latestMessage?.kind === 'ai' || latestMessage?.kind === 'staff') {
                savePendingReply(null);
                clearPendingReply();
                contactForm.hidden = true;
                clearTypingIndicator();
            }
            updatePendingMessageCount(result.pending_messages, result.max_pending_messages);
        } catch (error) {
            if (document.visibilityState === 'visible') {
                status.textContent = error instanceof Error ? error.message : 'Không thể đồng bộ cuộc trò chuyện.';
            }
        }
    }, 3000);
    window.setInterval(refreshStaffTyping, 1200);
    window.addEventListener('pagehide', stopCustomerTyping);
    updateComposerControls();
})();
