(function () {
  const config = window.mccWidgetConfig || {};

  function getSessionId(index) {
    const key = 'mcc_session_id_' + index;
    let value = window.localStorage.getItem(key);
    if (!value) {
      value = 'mcc_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
      window.localStorage.setItem(key, value);
    }
    return value;
  }

  function createElement(tag, className, text) {
    const el = document.createElement(tag);
    if (className) el.className = className;
    if (typeof text === 'string') el.textContent = text;
    return el;
  }

  function addMessage(container, text, type) {
    const row = createElement('div', 'mcc-message-row ' + type);
    const bubble = createElement('div', 'mcc-message ' + type);
    bubble.innerHTML = String(text).replace(/\n/g, '<br>');
    row.appendChild(bubble);
    container.appendChild(row);
    container.scrollTop = container.scrollHeight;
  }

  async function sendMessage(message, sessionId, messagesEl, inputEl, buttonEl) {
    buttonEl.disabled = true;
    inputEl.disabled = true;
    const previousLabel = buttonEl.textContent;
    buttonEl.textContent = config.sendingLabel || 'Pensando…';

    try {
      const response = await fetch(config.restUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          message,
          session_id: sessionId
        })
      });

      const data = await response.json();
      addMessage(messagesEl, data.reply || config.errorLabel || 'Ha ocurrido un error.', 'bot');
    } catch (error) {
      addMessage(messagesEl, config.errorLabel || 'Ha ocurrido un error.', 'bot');
    } finally {
      buttonEl.disabled = false;
      inputEl.disabled = false;
      buttonEl.textContent = previousLabel;
      inputEl.focus();
    }
  }

  function buildWidget(root, mode, index) {
    const shell = createElement('div', 'mcc-shell ' + mode);
    shell.style.setProperty('--mcc-primary', config.primaryColor || '#111827');

    if (mode === 'floating') {
      const toggle = createElement('button', 'mcc-toggle', config.buttonLabel || 'Reservar cita');
      toggle.type = 'button';

      const panel = createElement('div', 'mcc-panel is-hidden');
      shell.appendChild(toggle);
      shell.appendChild(panel);

      toggle.addEventListener('click', function () {
        panel.classList.toggle('is-hidden');
      });

      panel.appendChild(buildPanel(index));
    } else {
      shell.appendChild(buildPanel(index));
    }

    root.appendChild(shell);
  }

  function buildPanel(index) {
    const wrapper = createElement('div', 'mcc-panel-inner');
    const header = createElement('div', 'mcc-header');
    const title = createElement('div', 'mcc-title', config.widgetTitle || 'Reserva tu cita');
    header.appendChild(title);

    const messages = createElement('div', 'mcc-messages');

    const form = createElement('form', 'mcc-form');
    const input = createElement('input', 'mcc-input');
    input.type = 'text';
    input.placeholder = config.placeholder || 'Escribe tu mensaje…';

    const button = createElement('button', 'mcc-send', config.sendLabel || 'Enviar');
    button.type = 'submit';

    form.appendChild(input);
    form.appendChild(button);

    wrapper.appendChild(header);
    wrapper.appendChild(messages);
    wrapper.appendChild(form);

    const sessionId = getSessionId(index);

    addMessage(messages, config.welcomeMessage || 'Hola. ¿Qué servicio quieres reservar?', 'bot');
    addMessage(messages, config.introLabel || 'Para empezar, dime qué servicio quieres reservar.', 'bot');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      const value = input.value.trim();
      if (!value) return;

      addMessage(messages, value, 'user');
      input.value = '';
      sendMessage(value, sessionId, messages, input, button);
    });

    return wrapper;
  }

  function init() {
    const roots = document.querySelectorAll('[data-mcc-shell="1"]');
    if (!roots.length) return;

    roots.forEach(function (root, index) {
      if (root.dataset.mccReady === '1') return;
      root.dataset.mccReady = '1';
      const wrapper = root.closest('.mcc-widget-root');
      const mode = (wrapper && wrapper.dataset.mode === 'floating') || config.mode === 'floating' ? 'floating' : 'inline';
      buildWidget(root, mode, index);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
