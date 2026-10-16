(function () {
    'use strict';

    var currentScript = document.currentScript;
    var widgetKey = currentScript.getAttribute('data-widget-key');

    if (!widgetKey) {
        console.error('[KKP Chat Widget] data-widget-key tidak ditemukan pada tag <script>.');
        return;
    }

    // Origin tempat script ini dimuat (otomatis, tidak perlu dikonfigurasi manual)
    var scriptUrl = new URL(currentScript.src);
    var API_ORIGIN = scriptUrl.origin;

    var isOpen = false;
    var pendingIdentityToken = null;
    var iframeReady = false;

    // ---------- Bubble button ----------
    var bubble = document.createElement('button');
    bubble.setAttribute('aria-label', 'Buka asisten chat');
    var ICON_BOT = '<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="2.5" x2="12" y2="5.5"/><circle cx="12" cy="2.5" r="1" fill="#fff"/><rect x="4" y="5.5" width="16" height="12" rx="4"/><circle cx="9" cy="11" r="1.4" fill="#fff" stroke="none"/><circle cx="15" cy="11" r="1.4" fill="#fff" stroke="none"/><path d="M9.5 14.2c1.4 1 3.6 1 5 0"/><path d="M8 17.5v3l4-3"/></svg>';
    bubble.innerHTML = ICON_BOT;
    bubble.style.cssText = [
        'position:fixed', 'bottom:20px', 'right:20px', 'width:56px', 'height:56px',
        'border-radius:50%', 'background:#2d898d', 'color:white', 'border:none',
        'font-size:24px', 'cursor:pointer', 'box-shadow:0 4px 14px rgba(0,0,0,0.25)',
        'z-index:999999', 'display:flex', 'align-items:center', 'justify-content:center',
    ].join(';');

    // ---------- Iframe container ----------
    var container = document.createElement('div');
    container.style.cssText = [
        'position:fixed', 'bottom:88px', 'right:20px', 'width:380px', 'max-width:92vw',
        'height:560px', 'max-height:75vh', 'border-radius:16px', 'overflow:hidden',
        'box-shadow:0 8px 30px rgba(0,0,0,0.25)', 'z-index:999999', 'display:none',
        'background:white',
    ].join(';');

    var iframe = document.createElement('iframe');
    var CHAT_HTML_VERSION = '2'; // naikkan angka ini tiap kali chat.html diupdate, supaya cache browser tidak nyangkut
    iframe.src = API_ORIGIN + '/widget/chat.html?widget_key=' + encodeURIComponent(widgetKey) + '&v=' + CHAT_HTML_VERSION;
    iframe.style.cssText = 'width:100%;height:100%;border:none;';
    iframe.setAttribute('title', 'Asisten Chat');
    container.appendChild(iframe);

    iframe.addEventListener('load', function () {
        iframeReady = true;
        sendInitToIframe();
    });

    function sendInitToIframe() {
        iframe.contentWindow.postMessage({
            type: 'kkp-chat-init',
            pageOrigin: window.location.origin,
            identityToken: pendingIdentityToken,
        }, API_ORIGIN);
    }

    function toggle() {
        isOpen = !isOpen;
        container.style.display = isOpen ? 'block' : 'none';
        bubble.innerHTML = isOpen ? '✕' : ICON_BOT;
        if (isOpen) hidePopup();
    }

    bubble.addEventListener('click', toggle);

    // ---------- Popup ajakan (muncul tiap halaman dibuka/refresh) ----------
    var style = document.createElement('style');
    style.textContent = '@keyframes kkpPop{0%{opacity:0;transform:translateY(12px) scale(.9)}60%{transform:translateY(-3px) scale(1.03)}100%{opacity:1;transform:none}}' +
        '@keyframes kkpPulse{0%{box-shadow:0 0 0 0 rgba(45,137,141,.6)}100%{box-shadow:0 0 0 18px rgba(45,137,141,0)}}';
    document.head.appendChild(style);

    var popup = document.createElement('div');
    popup.style.cssText = [
        'position:fixed', 'bottom:88px', 'right:20px', 'max-width:240px', 'padding:12px 30px 12px 14px',
        'background:#fff', 'color:#1f2937', 'border-radius:14px', 'font:14px/1.4 system-ui,sans-serif',
        'box-shadow:0 6px 24px rgba(0,0,0,.2)', 'z-index:999999', 'cursor:pointer', 'display:none',
    ].join(';');
    popup.innerHTML = '<b>Halo! 👋</b><br>Saya adalah Layanan AI Informasi Biro SDM Aparatur dan Organisasi KKP' +
        '<span data-close style="position:absolute;top:6px;right:10px;font-size:16px;color:#9ca3af">×</span>';

    var popupTimer = null;
    function hidePopup() {
        clearTimeout(popupTimer);
        popup.style.display = 'none';
        bubble.style.animation = '';
    }
    function showPopup() {
        if (isOpen) return;
        popup.style.display = 'block';
        popup.style.animation = 'kkpPop .5s ease-out';
        bubble.style.animation = 'kkpPulse 1.4s ease-out 3';
        popupTimer = setTimeout(hidePopup, 10000);
    }
    popup.addEventListener('click', function (e) {
        hidePopup();
        if (!e.target.hasAttribute('data-close') && !isOpen) toggle();
    });

    document.body.appendChild(container);
    document.body.appendChild(popup);
    document.body.appendChild(bubble);
    setTimeout(showPopup, 1500);

    // ---------- API publik untuk situs yang memasang widget ----------
    // Dipanggil dari halaman situs lain (setelah user login di situs itu):
    //   KkpChatWidget.setIdentity('TOKEN_YANG_DIBUAT_BACKEND_ANDA');
    window.KkpChatWidget = {
        setIdentity: function (identityToken) {
            pendingIdentityToken = identityToken;
            if (iframeReady) {
                sendInitToIframe();
            }
        },
        open: function () {
            if (!isOpen) toggle();
        },
        close: function () {
            if (isOpen) toggle();
        },
    };

    // Beri tahu halaman yang memasang widget bahwa KkpChatWidget sudah siap
    // dipakai (misal untuk memanggil setIdentity() dengan aman).
    window.dispatchEvent(new CustomEvent('kkp-chat-widget-ready'));
})();