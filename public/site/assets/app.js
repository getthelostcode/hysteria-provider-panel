/* Hysteria2 Control — 站点交互（无依赖） */
(function () {
  'use strict';

  var nav = document.getElementById('nav');
  var toggle = document.getElementById('navToggle');
  var menu = document.getElementById('navMenu');
  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* 粘顶导航：滚动后加分隔线 */
  function onScroll() {
    if (nav) nav.classList.toggle('is-stuck', window.scrollY > 8);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* 移动端菜单 */
  if (toggle && menu) {
    toggle.addEventListener('click', function () {
      var open = menu.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? '关闭菜单' : '打开菜单');
    });

    menu.addEventListener('click', function (e) {
      if (e.target.closest('a')) {
        menu.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('is-open')) {
        menu.classList.remove('is-open');
        toggle.setAttribute('aria-expanded', 'false');
        toggle.focus();
      }
    });
  }

  /* 滚动出现 */
  var reveals = document.querySelectorAll('.reveal');
  if (!reduceMotion && 'IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }

  /* 复制按钮：优先 clipboard，非安全上下文降级 execCommand */
  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.top = '-1000px';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
    document.body.removeChild(ta);
    return ok;
  }

  document.querySelectorAll('.copy[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy') || '';
      var label = btn.textContent;

      function done(ok) {
        btn.textContent = ok ? '已复制' : '复制失败';
        btn.classList.toggle('is-done', ok);
        setTimeout(function () {
          btn.textContent = label;
          btn.classList.remove('is-done');
        }, 1600);
      }

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(function () { done(true); }, function () { done(fallbackCopy(text)); });
      } else {
        done(fallbackCopy(text));
      }
    });
  });

  /* 服务商列表：从面板公开 API 拉取 */
  var providerList = document.getElementById('providerList');
  if (providerList) {
    var endpoint = providerList.getAttribute('data-endpoint') || '/api/public/providers';

    fetch(endpoint + '?t=' + Date.now(), {
      headers: { Accept: 'application/json' },
      credentials: 'omit',
      cache: 'no-store'
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (!res.ok) {
            var err = new Error('http_' + res.status);
            err.data = data;
            throw err;
          }
          return data;
        });
      })
      .then(function (data) { renderProviders(providerList, (data && data.providers) || []); })
      .catch(function (err) {
        providerList.innerHTML = '';
        var note = document.createElement('p');
        note.className = 'providers-empty';
        note.textContent = (err && err.data && err.data.message)
          ? '服务商列表暂不可用：' + err.data.message
          : '服务商列表加载失败，稍后再试；也可以直接点右上角「注册服务商」。';
        providerList.appendChild(note);
      });
  }

  function renderProviders(container, providers) {
    container.innerHTML = '';

    if (!providers.length) {
      var empty = document.createElement('p');
      empty.className = 'providers-empty';
      empty.textContent = '暂时没有公开的服务商。你可以先领 10 GB 体验，或自己入驻成为服务商。';
      container.appendChild(empty);
      return;
    }

    providers.forEach(function (p) {
      var card = document.createElement('article');
      card.className = 'provider-card';

      var head = document.createElement('div');
      var name = document.createElement('h3');
      name.textContent = p.name || '未命名服务商';
      var code = document.createElement('span');
      code.className = 'provider-code';
      code.textContent = p.code || '';
      head.appendChild(name);
      head.appendChild(code);

      var stats = document.createElement('p');
      stats.className = 'provider-stats';
      stats.innerHTML = '节点 <b>' + Number(p.nodes || 0) + '</b> · 在线 <b>' + Number(p.online || 0) + '</b>';

      var actions = document.createElement('div');
      actions.className = 'provider-actions';

      var join = document.createElement('a');
      join.className = 'btn btn-primary';
      join.href = p.join_url || ('/join/' + encodeURIComponent(p.code || ''));
      join.textContent = '注册试用';

      var free = document.createElement('a');
      free.className = 'btn btn-ghost';
      free.href = p.free_url || ('free.html?code=' + encodeURIComponent(p.code || ''));
      free.textContent = '10 GB 体验';

      actions.appendChild(join);
      actions.appendChild(free);

      card.appendChild(head);
      card.appendChild(stats);
      card.appendChild(actions);
      container.appendChild(card);
    });
  }

  /* 年份 */
  var year = document.getElementById('year');
  if (year) year.textContent = String(new Date().getFullYear());
})();
