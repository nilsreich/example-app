/**
 * kiventro In-App-Feedback (Marker.io-Stil, lokal & sicher).
 *
 * Funktionsweise:
 *  - Floating Button öffnet ein Panel (Markup siehe resources/views/components/feedback-widget.blade.php).
 *  - "Element markieren": Hover-Highlighting, Klick wählt das Element und erfasst
 *    CSS-Selektor + Text (nur lokal, kein externer Dienst).
 *  - Screenshot: bevorzugt Browser-Screen-Capture (getDisplayMedia, ein Frame),
 *    Fallback: lokale Bilddatei wählen.
 *  - Versand per fetch an POST /feedback (CSRF-geschützt, gedrosselt).
 *
 * Bewusst ohne externe Bibliotheken/CDNs – alles wird lokal gebündelt (Vite).
 */
(() => {
    const csrfToken = () =>
        document.querySelector('meta[name="csrf-token"]')?.content ??
        decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

    const MAX_SCREENSHOT_BYTES = 2_500_000;

    function selectorFor(element) {
        if (element.id) {
            return '#' + CSS.escape(element.id);
        }

        const parts = [];
        let node = element;

        while (node && node.nodeType === 1 && node !== document.body && parts.length < 5) {
            let part = node.tagName.toLowerCase();

            const classes = [...node.classList].filter((name) => !name.startsWith('fw-')).slice(0, 2);
            if (classes.length > 0) {
                part += classes.map((name) => '.' + CSS.escape(name)).join('');
            }

            const parent = node.parentElement;
            if (parent) {
                const siblings = [...parent.children].filter((child) => child.tagName === node.tagName);
                if (siblings.length > 1) {
                    part += `:nth-of-type(${siblings.indexOf(node) + 1})`;
                }
            }

            parts.unshift(part);
            node = parent;
        }

        return parts.join(' > ');
    }

    async function captureScreen() {
        if (!navigator.mediaDevices?.getDisplayMedia) {
            throw new Error('Screen-Capture wird von diesem Browser nicht unterstützt.');
        }

        const stream = await navigator.mediaDevices.getDisplayMedia({
            video: { displaySurface: 'browser' },
            audio: false,
        });

        try {
            const video = document.createElement('video');
            video.srcObject = stream;
            await video.play();

            // Kurz warten, damit ein vollständiges Frame vorliegt.
            await new Promise((resolve) => setTimeout(resolve, 250));

            const canvas = document.createElement('canvas');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            return canvas.toDataURL('image/jpeg', 0.8);
        } finally {
            stream.getVideoTracks().forEach((track) => track.stop());
        }
    }

    function initWidget(root) {
        if (root.dataset.fwReady === '1') {
            return;
        }
        root.dataset.fwReady = '1';

        const $ = (selector) => root.querySelector(selector);
        const panel = $('[data-fw-panel]');
        const status = $('[data-fw-status]');
        const submitButton = $('[data-fw-submit]');
        const preview = $('[data-fw-preview]');
        const previewImage = $('[data-fw-preview-image]');
        const elementInfo = $('[data-fw-element-info]');

        let selectedElement = null;
        let screenshotDataUrl = null;
        let picking = false;
        let highlighted = null;

        const setStatus = (text, isError = false) => {
            status.textContent = text;
            status.classList.toggle('fw-error', isError);
        };

        const openPanel = () => {
            panel.hidden = false;
            root.querySelector('[data-fw-open]').setAttribute('aria-expanded', 'true');
        };

        const closePanel = () => {
            stopPicking();
            panel.hidden = true;
            root.querySelector('[data-fw-open]').setAttribute('aria-expanded', 'false');
        };

        const highlight = (element) => {
            if (highlighted === element) {
                return;
            }
            if (highlighted) {
                highlighted.style.outline = highlighted.dataset.fwOutline ?? '';
                delete highlighted.dataset.fwOutline;
            }
            if (element && element !== root) {
                element.dataset.fwOutline = element.style.outline ?? '';
                element.style.outline = '2px solid #f59e0b';
            }
            highlighted = element;
        };

        const onPointerMove = (event) => {
            if (picking) {
                highlight(event.target);
            }
        };

        const onPointerClick = (event) => {
            if (!picking) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            selectedElement = {
                selector: selectorFor(event.target),
                text: (event.target.innerText ?? '').replace(/\s+/g, ' ').trim().slice(0, 300),
            };

            elementInfo.textContent = `Markiert: ${selectedElement.selector}`;
            stopPicking();
        };

        const onKeyDown = (event) => {
            if (event.key === 'Escape') {
                if (picking) {
                    stopPicking();
                } else if (!panel.hidden) {
                    closePanel();
                }
            }
        };

        function startPicking() {
            picking = true;
            root.classList.add('fw-picking');
            $('[data-fw-pick]').classList.add('fw-active');
            setStatus('Element im Hintergrund anklicken (Esc bricht ab).');
        }

        function stopPicking() {
            picking = false;
            root.classList.remove('fw-picking');
            $('[data-fw-pick]').classList.remove('fw-active');
            highlight(null);
        }

        // --- Events ---
        root.querySelector('[data-fw-open]').addEventListener('click', () => {
            panel.hidden ? openPanel() : closePanel();
        });
        root.querySelector('[data-fw-close]').addEventListener('click', closePanel);
        $('[data-fw-pick]').addEventListener('click', () => (picking ? stopPicking() : startPicking()));
        $('[data-fw-clear-element]').addEventListener('click', () => {
            selectedElement = null;
            elementInfo.textContent = 'Kein Element markiert.';
        });

        $('[data-fw-shot]').addEventListener('click', async () => {
            setStatus('Screenshot wird aufgenommen …');
            try {
                screenshotDataUrl = await captureScreen();
                showPreview();
                setStatus('Screenshot aufgenommen.');
            } catch (error) {
                setStatus(`${error.message} Alternativ Bilddatei wählen.`, true);
            }
        });

        $('[data-fw-file]').addEventListener('change', (event) => {
            const file = event.target.files?.[0];
            if (!file) {
                return;
            }
            if (file.size > MAX_SCREENSHOT_BYTES) {
                setStatus('Bild ist zu groß (max. 2,5 MB).', true);
                return;
            }
            const reader = new FileReader();
            reader.onload = () => {
                screenshotDataUrl = reader.result;
                showPreview();
                setStatus('Bild angehängt.');
            };
            reader.readAsDataURL(file);
        });

        $('[data-fw-remove-shot]').addEventListener('click', () => {
            screenshotDataUrl = null;
            previewImage.removeAttribute('src');
            preview.hidden = true;
        });

        function showPreview() {
            previewImage.src = screenshotDataUrl;
            preview.hidden = false;
        }

        submitButton.addEventListener('click', async () => {
            const category = $('[data-fw-category]').value;
            const message = $('[data-fw-message]').value.trim();

            if (message.length < 3) {
                setStatus('Bitte kurz beschreiben, worum es geht (min. 3 Zeichen).', true);
                return;
            }

            submitButton.disabled = true;
            setStatus('Wird gesendet …');

            try {
                const response = await fetch(root.dataset.fwEndpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                    },
                    body: JSON.stringify({
                        category,
                        message,
                        page_url: window.location.href,
                        page_title: document.title,
                        element_selector: selectedElement?.selector ?? null,
                        element_text: selectedElement?.text ?? null,
                        browser_info: {
                            userAgent: navigator.userAgent,
                            language: navigator.language,
                            viewport: `${window.innerWidth}×${window.innerHeight}`,
                        },
                        screenshot: screenshotDataUrl,
                    }),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => ({}));
                    throw new Error(payload.message ?? `Fehler ${response.status}`);
                }

                setStatus('Danke! Dein Feedback ist eingegangen.');
                $('[data-fw-message]').value = '';
                selectedElement = null;
                elementInfo.textContent = 'Kein Element markiert.';
                screenshotDataUrl = null;
                preview.hidden = true;
                submitButton.disabled = true;
                setTimeout(() => {
                    closePanel();
                    submitButton.disabled = false;
                    setStatus('');
                }, 1200);
            } catch (error) {
                setStatus(error.message, true);
                submitButton.disabled = false;
            }
        });

        $('[data-fw-message]').addEventListener('input', (event) => {
            submitButton.disabled = event.target.value.trim().length < 3;
        });

        document.addEventListener('mousemove', onPointerMove, true);
        document.addEventListener('click', onPointerClick, true);
        document.addEventListener('keydown', onKeyDown);

        submitButton.disabled = true;
    }

    const boot = () => document.querySelectorAll('[data-feedback-widget]').forEach(initWidget);

    document.addEventListener('DOMContentLoaded', boot);
    // Livewire-Navigation (wire:navigate) ersetzt den DOM ohne Reload.
    document.addEventListener('livewire:navigated', boot);
})();
