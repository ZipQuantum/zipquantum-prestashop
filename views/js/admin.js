/**
 * ZipQuantum Smart Links and QR Codes integration for PrestaShop.
 *
 * @author Xaere
 * @copyright 2026 Xaere
 * @license https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

(function () {
  'use strict';

  function initialize() {
  var root = document.querySelector('.zqps-shell');
  if (!root) {
    return;
  }
  var endpoint = root.getAttribute('data-zq-ajax-url');
  var message = root.querySelector('.zqps-message');

  function setMessage(text, isError) {
    if (!message) {
      return;
    }
    message.textContent = text;
    message.className = 'zqps-message ' + (isError ? 'text-danger' : 'text-success');
  }

  function call(action, values) {
    var data = new FormData();
    data.append('ajax', '1');
    data.append('action', action);
    Object.keys(values || {}).forEach(function (key) {
      data.append(key, values[key]);
    });
    return fetch(endpoint, {
      method: 'POST',
      credentials: 'same-origin',
      body: data,
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.success) {
          throw new Error(payload.error && payload.error.message ? payload.error.message : 'ZipQuantum request failed.');
        }
        return payload.data || {};
      });
    });
  }

  function poll(interval) {
    window.setTimeout(function () {
      call('oauthPoll').then(function (data) {
        if (data.status === 'connected') {
          setMessage('ZipQuantum is connected.', false);
          window.location.reload();
          return;
        }
        poll(interval);
      }).catch(function (error) {
        setMessage(error.message, true);
      });
    }, interval * 1000);
  }

  root.querySelectorAll('.zqps-oauth').forEach(function (button) {
    button.addEventListener('click', function () {
      // Open synchronously while the click still carries user activation.
      // Opening only after the handshake fetch resolves is blocked by Chrome,
      // Safari and Firefox popup protection.
      var popup = window.open('', 'zipquantum-oauth', 'width=760,height=760');
      if (!popup) {
        setMessage('Allow pop-ups, then try again.', true);
        return;
      }
      popup.document.title = 'Connecting to ZipQuantum';
      popup.document.body.textContent = 'Preparing the secure ZipQuantum connection...';
      button.disabled = true;
      call('oauthStart', {intent: button.getAttribute('data-intent') || 'connect'}).then(function (data) {
        try {
          popup.opener = null;
        } catch (ignored) {
          // Some browsers prevent access once the popup changes origin.
        }
        popup.location.replace(data.authorization_url);
        setMessage('Waiting for ZipQuantum authorization...', false);
        poll(Math.max(3, Number(data.interval || 3)));
      }).catch(function (error) {
        popup.close();
        button.disabled = false;
        setMessage(error.message, true);
      });
    });
  });

  root.querySelectorAll('.zqps-object').forEach(function (button) {
    button.addEventListener('click', function () {
      var values = {
        object_type: root.querySelector('#zqps-object-type').value,
        object_id: root.querySelector('#zqps-object-id').value
      };
      if (button.getAttribute('data-action') === 'attach') {
        values.link_id = root.querySelector('#zqps-link-id').value;
      }
      button.disabled = true;
      call(button.getAttribute('data-action'), values).then(function () {
        window.location.reload();
      }).catch(function (error) {
        button.disabled = false;
        setMessage(error.message, true);
      });
    });
  });

  root.querySelectorAll('.zqps-action').forEach(function (button) {
    button.addEventListener('click', function () {
      var action = button.getAttribute('data-action');
      var values = {};
      if (action === 'bulkEnqueue') {
        values.object_type = root.querySelector('#zqps-bulk-type').value;
      }
      button.disabled = true;
      call(action, values).then(function () {
        window.location.reload();
      }).catch(function (error) {
        button.disabled = false;
        setMessage(error.message, true);
      });
    });
  });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initialize);
  } else {
    initialize();
  }
}());
