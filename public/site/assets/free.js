/* free.html：识别码 → 服务商注册页 */
(function () {
  'use strict';

  var form = document.getElementById('claimForm');
  var input = document.getElementById('code');
  var hint = document.getElementById('claimHint');
  if (!form || !input) return;

  var CODE_RE = /^[a-z0-9][a-z0-9_-]{2,31}$/;

  function normalize(value) {
    return (value || '').trim().toLowerCase();
  }

  function error(message) {
    if (!hint) return;
    hint.textContent = message;
    hint.classList.add('is-error');
  }

  function clearError() {
    if (!hint) return;
    hint.classList.remove('is-error');
    hint.textContent = '还没有识别码？向给你链接的服务商索取，或让服务商分享带识别码的体验页。';
  }

  /* 服务商分享的 free.html?code=xxx，打开就填好 */
  var params = new URLSearchParams(location.search);
  var preset = normalize(params.get('code'));
  if (preset) {
    input.value = preset;
    if (CODE_RE.test(preset)) {
      hint.textContent = '已带入识别码 ' + preset + '，点「去注册」继续。';
    }
  }

  input.addEventListener('input', clearError);

  form.addEventListener('submit', function (event) {
    event.preventDefault();
    var code = normalize(input.value);

    if (!CODE_RE.test(code)) {
      error('识别码格式不对：小写字母/数字，3–32 位，可带 - 或 _');
      input.focus();
      return;
    }

    clearError();
    location.href = '/join/' + encodeURIComponent(code);
  });
})();
