/**
 * Shared Zoom Client View — lazy asset load, lightweight init, screen-share layout.
 * Scripts load after boot screen (avoids blocking parse + OOM on low-memory tabs).
 */
(function (global) {
  'use strict';

  var shareLayoutApplied = false;
  var shareListenersBound = false;
  var participantListenersBound = false;
  var dockTimer = null;
  var assetsLoadPromise = null;
  var preparePromise = null;
  var initPromise = null;
  var avatarObserver = null;
  var avatarRefreshTimer = null;
  var avatarLookup = {};
  var avatarBrandingConfig = null;

  function normalizeName(value) {
    return String(value || '').trim().toLowerCase().replace(/\s+/g, ' ');
  }

  function getInitials(name) {
    if (!name || typeof name !== 'string') return 'U';

    var words = name.trim().split(/\s+/).filter(Boolean);
    if (words.length === 0) return 'U';
    if (words.length === 1) return words[0].slice(0, 2).toUpperCase();

    return (words[0][0] + words[1][0]).toUpperCase();
  }

  /** @deprecated use getInitials */
  function initialsFor(name) {
    return getInitials(name);
  }

  function colorForName(name) {
    var palette = ['#1e4d2b', '#3661B9', '#0e72ed', '#7c3aed', '#b45309', '#be123c', '#0f766e'];
    var key = normalizeName(name);
    var hash = 0;
    for (var i = 0; i < key.length; i++) {
      hash = ((hash << 5) - hash) + key.charCodeAt(i);
      hash |= 0;
    }
    return palette[Math.abs(hash) % palette.length];
  }

  function buildAvatarLookup(branding) {
    var map = {};
    if (!branding) return map;
    if (branding.self && branding.self.name) {
      map[normalizeName(branding.self.name)] = branding.self.avatar_url || '';
    }
    (branding.participants || []).forEach(function (p) {
      if (!p || !p.name) return;
      map[normalizeName(p.name)] = p.avatar_url || '';
    });
    return map;
  }

  function resolveAvatarUrl(name) {
    var key = normalizeName(name);
    if (avatarLookup[key]) return avatarLookup[key];
    var keys = Object.keys(avatarLookup);
    for (var i = 0; i < keys.length; i++) {
      var candidate = keys[i];
      if (key.indexOf(candidate) !== -1 || candidate.indexOf(key) !== -1) {
        return avatarLookup[candidate];
      }
    }
    return '';
  }

  function frameHasActiveVideo(frame) {
    if (!frame) return false;
    var videos = frame.querySelectorAll('video');
    for (var i = 0; i < videos.length; i++) {
      var video = videos[i];
      if (!video) continue;
      var rect = video.getBoundingClientRect();
      if (rect.width < 24 || rect.height < 24) continue;
      if (!video.paused && video.readyState >= 2 && video.videoWidth > 0) {
        return true;
      }
    }
    return false;
  }

  function isFooterOrNameTag(el) {
    if (!el || !el.closest) return false;
    return !!el.closest(
      '[class*="footer"], [class*="Footer"], [class*="name-card"], [class*="name-tag"], [class*="NameTag"]'
    );
  }

  function extractParticipantName(frame) {
    if (!frame) return '';

    var footerSelectors = [
      '[class*="video-footer"] span',
      '[class*="video-footer"] [class*="name"]',
      '[class*="footer-bar"] span',
      '[class*="footer"] [class*="name"]',
      '[class*="name-card"]',
      '[class*="participant-name"]',
      '[class*="ParticipantName"]',
    ];
    for (var f = 0; f < footerSelectors.length; f++) {
      var footers = frame.querySelectorAll(footerSelectors[f]);
      for (var j = 0; j < footers.length; j++) {
        var footText = (footers[j].textContent || '').trim();
        if (footText.length >= 2 && footText.length <= 80) return footText;
      }
    }

    var selectors = [
      '[class*="participant-name"]',
      '[class*="ParticipantName"]',
      '[class*="video-avatar__avatar-title"]',
      '[class*="avatar-name"]',
      '[class*="AvatarName"]',
      '[class*="avatar-title"]',
    ];
    for (var s = 0; s < selectors.length; s++) {
      var nodes = frame.querySelectorAll(selectors[s]);
      for (var i = 0; i < nodes.length; i++) {
        if (isFooterOrNameTag(nodes[i])) continue;
        var text = (nodes[i].textContent || '').trim();
        if (text.length >= 2 && text.length <= 80) return text;
      }
    }

    var labelled = frame.querySelector('[aria-label]');
    if (labelled && !isFooterOrNameTag(labelled)) {
      var label = (labelled.getAttribute('aria-label') || '').trim();
      if (label.length >= 2 && label.length <= 80) return label;
    }
    return '';
  }

  function findAvatarTileRoot(node, root) {
    var current = node;
    var best = null;
    var bestArea = 0;
    while (current && current !== root) {
      if (current.className && typeof current.className === 'string') {
        var cls = current.className;
        if (
          cls.indexOf('video-frame') !== -1 ||
          cls.indexOf('VideoFrame') !== -1 ||
          cls.indexOf('video-avatar') !== -1 ||
          cls.indexOf('VideoAvatar') !== -1
        ) {
          var rect = current.getBoundingClientRect();
          var area = rect.width * rect.height;
          if (rect.width >= 64 && rect.height >= 48 && area > bestArea) {
            best = current;
            bestArea = area;
          }
        }
      }
      current = current.parentElement;
    }
    return best || node;
  }

  function suppressSdkNamePlates(frame) {
    if (!frame || frameHasActiveVideo(frame)) return;

    var children = frame.querySelectorAll('*');
    for (var i = 0; i < children.length; i++) {
      var el = children[i];
      if (!el || el.classList.contains('fm-avatar-overlay') || el.closest('.fm-avatar-overlay')) continue;
      if (isFooterOrNameTag(el)) continue;
      if (el.tagName === 'VIDEO' || el.tagName === 'CANVAS' || el.tagName === 'IMG') continue;

      var text = (el.textContent || '').trim();
      if (text.length < 2 || text.length > 120) continue;

      var rect = el.getBoundingClientRect();
      if (rect.width < 40 || rect.height < 24) continue;

      var style = window.getComputedStyle(el);
      var fontSize = parseFloat(style.fontSize) || 0;
      var isLeafy = el.children.length === 0 || (el.children.length === 1 && el.children[0].tagName === 'SPAN');
      var looksLikeNamePlate =
        fontSize >= 22 ||
        (rect.height >= 72 && rect.width >= 100 && text.split(/\s+/).length <= 12);

      if (looksLikeNamePlate && isLeafy) {
        el.style.setProperty('visibility', 'hidden', 'important');
        el.style.setProperty('opacity', '0', 'important');
        el.style.setProperty('font-size', '0', 'important');
        el.style.setProperty('color', 'transparent', 'important');
        el.setAttribute('data-fm-name-plate-hidden', '1');
      }
    }
  }

  function patchSdkAvatarImages(root) {
    if (!root) return;
    var images = root.querySelectorAll('[class*="avatar"] img, [class*="Avatar"] img, img[src*="zoom.us"], img[src*="zoomcdn"], img[src*="gravatar"]');
    for (var i = 0; i < images.length; i++) {
      var img = images[i];
      if (!img || img.getAttribute('data-fm-avatar-patched') === '1') continue;
      img.referrerPolicy = 'no-referrer';
      img.crossOrigin = 'anonymous';
      img.style.objectFit = 'cover';
      img.style.borderRadius = '9999px';
      img.setAttribute('data-fm-avatar-patched', '1');
    }
  }

  function ensureAvatarOverlay(frame, name) {
    if (!frame || !name) return;
    var hasVideo = frameHasActiveVideo(frame);
    frame.classList.toggle('fm-has-active-video', hasVideo);
    frame.classList.add('fm-avatar-enhanced');

    if (hasVideo) {
      var existing = frame.querySelector('.fm-avatar-overlay');
      if (existing) existing.style.display = 'none';
      return;
    }

    suppressSdkNamePlates(frame);

    var overlay = frame.querySelector('.fm-avatar-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'fm-avatar-overlay';
      overlay.setAttribute('aria-hidden', 'true');
      frame.appendChild(overlay);
    }
    overlay.style.display = 'flex';

    var existingKey = overlay.getAttribute('data-fm-name');
    if (existingKey === normalizeName(name) && overlay.querySelector('.fm-avatar-circle')) {
      return;
    }
    overlay.setAttribute('data-fm-name', normalizeName(name));
    overlay.innerHTML = '';

    var content = document.createElement('div');
    content.className = 'fm-avatar-content';

    var circle = document.createElement('div');
    circle.className = 'fm-avatar-circle';
    var avatarUrl = resolveAvatarUrl(name);

    if (avatarUrl) {
      var img = document.createElement('img');
      img.className = 'fm-avatar-img';
      img.alt = name;
      img.referrerPolicy = 'no-referrer';
      img.crossOrigin = 'anonymous';
      img.src = avatarUrl;
      img.onerror = function () {
        img.remove();
        circle.classList.add('fm-avatar-initials');
        circle.style.background = colorForName(name);
        circle.textContent = getInitials(name);
      };
      circle.appendChild(img);
    } else {
      circle.classList.add('fm-avatar-initials');
      circle.style.background = colorForName(name);
      circle.textContent = getInitials(name);
    }

    var nameEl = document.createElement('div');
    nameEl.className = 'fm-avatar-name';
    nameEl.textContent = name;
    nameEl.title = name;

    content.appendChild(circle);
    content.appendChild(nameEl);
    overlay.appendChild(content);
  }

  function enhanceVideoFrames(root) {
    if (!root) return;
    patchSdkAvatarImages(root);

    var seen = new Set();
    var candidates = root.querySelectorAll(
      '[class*="video-frame"], [class*="VideoFrame"], [class*="video-avatar"], [class*="VideoAvatar"]'
    );

    for (var i = 0; i < candidates.length; i++) {
      var candidate = candidates[i];
      if (!candidate || candidate.closest('[class*="share"], [class*="Share"]')) continue;

      var tile = findAvatarTileRoot(candidate, root);
      if (!tile || seen.has(tile)) continue;

      var rect = tile.getBoundingClientRect();
      if (rect.width < 64 || rect.height < 48) continue;

      var name = extractParticipantName(tile);
      if (!name) continue;

      seen.add(tile);
      ensureAvatarOverlay(tile, name);
    }

    var anyCameraOff = root.querySelector('.fm-avatar-enhanced:not(.fm-has-active-video)');
    document.body.classList.toggle('fm-zoom-camera-off', !!anyCameraOff);
  }

  function refreshAvatarLookupFromZoom() {
    if (typeof ZoomMtg === 'undefined' || typeof ZoomMtg.getAttendeeslist !== 'function') return;
    try {
      ZoomMtg.getAttendeeslist({
        success: function (list) {
          if (!Array.isArray(list)) return;
          list.forEach(function (att) {
            if (!att) return;
            var name = att.userName || att.displayName || '';
            var avatar = att.avatar || att.picUrl || att.profilePicture || '';
            if (name && avatar) {
              avatarLookup[normalizeName(name)] = avatar;
            }
          });
          enhanceVideoFrames(document.getElementById('zmmtg-root'));
        }
      });
    } catch (e) { /* ignore */ }
  }

  function startAvatarEnhancer(branding) {
    stopAvatarEnhancer();
    avatarBrandingConfig = branding || null;
    avatarLookup = buildAvatarLookup(branding);
    var root = document.getElementById('zmmtg-root');
    if (!root) return;

    enhanceVideoFrames(root);
    refreshAvatarLookupFromZoom();

    avatarObserver = new MutationObserver(function () {
      if (avatarRefreshTimer) window.clearTimeout(avatarRefreshTimer);
      avatarRefreshTimer = window.setTimeout(function () {
        avatarRefreshTimer = null;
        enhanceVideoFrames(root);
      }, 120);
    });
    avatarObserver.observe(root, { childList: true, subtree: true, attributes: true });

    window.setInterval(function () {
      enhanceVideoFrames(root);
      refreshAvatarLookupFromZoom();
    }, 2500);
  }

  function stopAvatarEnhancer() {
    if (avatarObserver) {
      avatarObserver.disconnect();
      avatarObserver = null;
    }
    if (avatarRefreshTimer) {
      window.clearTimeout(avatarRefreshTimer);
      avatarRefreshTimer = null;
    }
  }

  function errMsg(err) {
    if (!err || typeof err !== 'object') return 'Zoom error';
    return err.reason || err.message || 'Zoom error';
  }

  function loadStylesheet(href) {
    return new Promise(function (resolve, reject) {
      if (document.querySelector('link[data-fm-zoom-css="1"]')) {
        resolve();
        return;
      }
      var link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      link.setAttribute('data-fm-zoom-css', '1');
      link.onload = function () { resolve(); };
      link.onerror = function () { reject(new Error('Failed to load Zoom CSS')); };
      document.head.appendChild(link);
    });
  }

  function loadScript(src) {
    return new Promise(function (resolve, reject) {
      var existing = document.querySelector('script[data-fm-zoom-src="' + src + '"]');
      if (existing) {
        if (existing.getAttribute('data-fm-loaded') === '1') {
          resolve();
          return;
        }
        existing.addEventListener('load', function () { resolve(); }, { once: true });
        existing.addEventListener('error', function () { reject(new Error('Failed to load ' + src)); }, { once: true });
        return;
      }
      var script = document.createElement('script');
      script.src = src;
      script.async = false;
      script.setAttribute('data-fm-zoom-src', src);
      script.onload = function () {
        script.setAttribute('data-fm-loaded', '1');
        resolve();
      };
      script.onerror = function () { reject(new Error('Failed to load ' + src)); };
      document.head.appendChild(script);
    });
  }

  /**
   * Load React + Zoom SDK only after boot UI is visible (sequential, one bundle at a time).
   */
  function loadZoomAssets(assetBase, meetingJs, zoomCssHref) {
    if (typeof ZoomMtg !== 'undefined') {
      return Promise.resolve();
    }
    if (assetsLoadPromise) {
      return assetsLoadPromise;
    }

    var base = String(assetBase || '').replace(/\/$/, '');
    var jsFile = meetingJs || 'zoom-meeting-6.2.0.min.js';
    var cssHref = zoomCssHref || (base + '/dist/ui/zoom-meetingsdk.css');

    assetsLoadPromise = loadStylesheet(cssHref)
      .then(function () { return loadScript(base + '/vendor/react.min.js'); })
      .then(function () { return loadScript(base + '/vendor/react-dom.min.js'); })
      .then(function () { return loadScript(base + '/vendor/redux.min.js'); })
      .then(function () { return loadScript(base + '/vendor/redux-thunk.min.js'); })
      .then(function () { return loadScript(base + '/dist/' + jsFile); })
      .then(function () {
        if (typeof ZoomMtg === 'undefined') {
          throw new Error('Zoom SDK failed to initialize.');
        }
      });

    return assetsLoadPromise;
  }

  function debouncedDock() {
    if (dockTimer) window.clearTimeout(dockTimer);
    dockTimer = window.setTimeout(function () {
      dockTimer = null;
      dockParticipantTiles();
    }, 800);
  }

  function dockParticipantTiles() {
    var root = document.getElementById('zmmtg-root');
    if (!root) return;

    var nodes = root.querySelectorAll(
      '[class*="video-avatar"], [class*="VideoAvatar"], [class*="self-video"], [class*="SelfVideo"]'
    );
    for (var i = 0; i < nodes.length && i < 8; i++) {
      var el = nodes[i];
      if (!el || el.closest('[class*="share"], [class*="Share"]')) continue;
      var rect = el.getBoundingClientRect();
      if (rect.width < 40 || rect.height < 40) continue;
      if (rect.width > window.innerWidth * 0.6) continue;
      el.classList.add('fm-docked-participant-tile');
    }
  }

  function isShareContentVisible() {
    var root = document.getElementById('zmmtg-root');
    if (!root) return false;
    var share = root.querySelector(
      '[class*="share-content"], [class*="ShareContent"], [class*="sharing-layout"], [class*="SharingLayout"]'
    );
    if (!share) return false;
    var rect = share.getBoundingClientRect();
    return rect.width > 80 && rect.height > 80;
  }

  function applyPureShareLayout() {
    if (!isShareContentVisible()) {
      clearShareLayout();
      return;
    }
    if (shareLayoutApplied) {
      debouncedDock();
      return;
    }
    shareLayoutApplied = true;
    document.body.classList.add('fm-zoom-share-active');
    if (typeof ZoomMtg !== 'undefined' && typeof ZoomMtg.showPureSharingContent === 'function') {
      try {
        ZoomMtg.showPureSharingContent({ show: true });
      } catch (e) { /* ignore */ }
    }
    debouncedDock();
  }

  function clearShareLayout() {
    shareLayoutApplied = false;
    document.body.classList.remove('fm-zoom-share-active');
    var root = document.getElementById('zmmtg-root');
    if (root) {
      root.querySelectorAll('.fm-docked-participant-tile').forEach(function (el) {
        el.classList.remove('fm-docked-participant-tile');
      });
    }
    if (typeof ZoomMtg !== 'undefined' && typeof ZoomMtg.showPureSharingContent === 'function') {
      try {
        ZoomMtg.showPureSharingContent({ show: false });
      } catch (e) { /* ignore */ }
    }
  }

  function setParticipantCountClasses(count) {
    document.body.classList.toggle('fm-multi-participant', count >= 2);
    document.body.classList.toggle('fm-participant-count-2', count === 2);
    document.body.classList.toggle('fm-participant-count-3plus', count >= 3);
    if (count >= 3) {
      ensureGalleryView(count);
    } else {
      document.body.classList.remove('fm-gallery-active');
    }
  }

  function isCrossOriginIsolated() {
    return typeof window.crossOriginIsolated !== 'undefined' && window.crossOriginIsolated === true;
  }

  function clickGalleryViewButton() {
    var root = document.getElementById('zmmtg-root');
    if (!root) return false;

    var candidates = root.querySelectorAll('button, [role="button"], [role="menuitem"]');
    for (var i = 0; i < candidates.length; i++) {
      var el = candidates[i];
      var label = (
        el.getAttribute('aria-label') ||
        el.getAttribute('title') ||
        el.textContent ||
        ''
      ).toLowerCase();
      if (label.indexOf('gallery') !== -1 && label.indexOf('side-by-side') === -1) {
        el.click();
        return true;
      }
    }
    return false;
  }

  var galleryEnsureTimer = null;
  function ensureGalleryView(count) {
    if (count < 3) return;

    document.body.classList.add('fm-participant-count-3plus', 'fm-gallery-active');
    document.body.classList.toggle('fm-gallery-blocked', !isCrossOriginIsolated());

    if (!isCrossOriginIsolated()) {
      return;
    }

    if (galleryEnsureTimer) window.clearTimeout(galleryEnsureTimer);
    galleryEnsureTimer = window.setTimeout(function () {
      galleryEnsureTimer = null;
      clickGalleryViewButton();
      window.setTimeout(clickGalleryViewButton, 1500);
    }, 600);
  }

  function applyVideoOrderLayout(data) {
    if (!data) return;
    var view = data.view || '';
    if (view === 'gallery-view') {
      document.body.classList.add('fm-gallery-active');
    }
    var galleryCount = Array.isArray(data.galleryMainCurrent) ? data.galleryMainCurrent.length : 0;
    var barCount = Array.isArray(data.speakerBarCurrent) ? data.speakerBarCurrent.length : 0;
    var activeCount = Array.isArray(data.speakerActiveCurrent) ? data.speakerActiveCurrent.length : 0;
    var total = Math.max(galleryCount, barCount + activeCount);
    if (total >= 3) {
      setParticipantCountClasses(total);
    }
  }

  function refreshParticipantLayout() {
    if (typeof ZoomMtg === 'undefined' || typeof ZoomMtg.getAttendeeslist !== 'function') return;
    try {
      ZoomMtg.getAttendeeslist({
        success: function (list) {
          var count = Array.isArray(list) ? list.length : 0;
          setParticipantCountClasses(count);
        }
      });
    } catch (e) { /* ignore */ }
  }

  function bindParticipantViewListeners() {
    if (participantListenersBound || typeof ZoomMtg === 'undefined') return;
    if (typeof ZoomMtg.inMeetingServiceListener !== 'function') return;
    participantListenersBound = true;

    try {
      ZoomMtg.inMeetingServiceListener('onUserJoin', function () {
        window.setTimeout(refreshParticipantLayout, 400);
      });
    } catch (e) { /* ignore */ }

    try {
      ZoomMtg.inMeetingServiceListener('onUserLeave', function () {
        window.setTimeout(refreshParticipantLayout, 400);
      });
    } catch (e) { /* ignore */ }

    try {
      ZoomMtg.inMeetingServiceListener('onVideoOrder', function (data) {
        applyVideoOrderLayout(data);
      });
    } catch (e) { /* ignore */ }

    try {
      ZoomMtg.inMeetingServiceListener('onMeetingStatus', function (data) {
        if (data && data.status === 2) {
          window.setTimeout(refreshParticipantLayout, 1200);
        }
      });
    } catch (e) { /* ignore */ }
  }

  function bindShareListeners() {
    if (shareListenersBound || typeof ZoomMtg === 'undefined') return;
    if (typeof ZoomMtg.inMeetingServiceListener !== 'function') return;
    shareListenersBound = true;

    try {
      ZoomMtg.inMeetingServiceListener('receiveSharingChannelReady', function () {
        applyPureShareLayout();
      });
    } catch (e) { /* ignore */ }

    try {
      ZoomMtg.inMeetingServiceListener('onShareContentChange', function () {
        if (isShareContentVisible()) {
          applyPureShareLayout();
        } else {
          clearShareLayout();
          refreshParticipantLayout();
        }
      });
    } catch (e) { /* ignore */ }

    try {
      ZoomMtg.inMeetingServiceListener('onMeetingStatus', function (data) {
        if (data && data.status === 3) {
          clearShareLayout();
          setParticipantCountClasses(0);
        }
      });
    } catch (e) { /* ignore */ }
  }

  function stopShareWatch() {
    if (dockTimer) {
      window.clearTimeout(dockTimer);
      dockTimer = null;
    }
    stopAvatarEnhancer();
    clearShareLayout();
    setParticipantCountClasses(0);
    shareListenersBound = false;
    participantListenersBound = false;
  }

  function showZoomRoot() {
    document.documentElement.classList.add('zoom-client-meeting-active');
    document.body.classList.add('zoom-client-meeting-active');
    var root = document.getElementById('zmmtg-root');
    if (root) root.style.display = 'block';
  }

  function getInitOptions(leaveUrl) {
    return {
      leaveUrl: leaveUrl,
      patchJsMedia: true,
      leaveOnPageUnload: true,
      showPureSharingContent: false,
      sharingMode: 'fit',
      defaultView: 'gallery',
      videoDrag: true,
      videoHeader: true,
      isLockBottom: true,
      disablePreview: true,
      enableHD: false,
      enableFullHD: false,
      isSupportPolling: false,
      isSupportQA: false,
      isSupportBreakout: false,
      isSupportSimulive: false
    };
  }

  function prepareSdk(zoomLibUrl) {
    if (preparePromise) return preparePromise;

    preparePromise = new Promise(function (resolve, reject) {
      var settled = false;
      function finish(ok, val) {
        if (settled) return;
        settled = true;
        ok ? resolve(val) : reject(val);
      }

      var timeout = window.setTimeout(function () {
        preparePromise = null;
        finish(false, new Error('Zoom audio/video preparation timed out. Refresh and try again.'));
      }, 45000);

      try {
        if (typeof ZoomMtg.setZoomJSLib === 'function') {
          ZoomMtg.setZoomJSLib(zoomLibUrl, '/av');
        }
        ZoomMtg.preLoadWasm();
        var prep = ZoomMtg.prepareWebSDK();
        if (prep && typeof prep.then === 'function') {
          prep.then(function () {
            window.clearTimeout(timeout);
            finish(true);
          }).catch(function (err) {
            window.clearTimeout(timeout);
            preparePromise = null;
            finish(false, err);
          });
        } else {
          window.setTimeout(function () {
            window.clearTimeout(timeout);
            finish(true);
          }, 300);
        }
      } catch (e) {
        window.clearTimeout(timeout);
        preparePromise = null;
        finish(false, e);
      }
    });

    return preparePromise;
  }

  function initClient(leaveUrl) {
    if (initPromise) return initPromise;

    initPromise = new Promise(function (resolve, reject) {
      var opts = getInitOptions(leaveUrl);
      opts.success = function () {
        bindShareListeners();
        bindParticipantViewListeners();
        window.addEventListener('beforeunload', stopShareWatch, { once: true });
        resolve();
      };
      opts.error = function (err) {
        initPromise = null;
        reject(new Error(errMsg(err)));
      };
      ZoomMtg.init(opts);
    });

    return initPromise;
  }

  function fetchHostZak() {
    return fetch('fm_meeting_host_zak.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok && d.zak) return d.zak;
        return '';
      })
      .catch(function () { return ''; });
  }

  /**
   * Full boot flow: lazy assets → WASM → init → optional ZAK → join.
   * @param {object} cfg
   */
  function startMeeting(cfg) {
    var sdk = cfg.sdk;
    var leaveUrl = cfg.leaveUrl;
    var zoomLibUrl = cfg.zoomLibUrl;
    var assetBase = cfg.assetBase;
    var meetingJs = cfg.meetingJs;
    var zoomCssHref = cfg.zoomCssHref;
    var isHost = !!cfg.isHost;
    var onStatus = typeof cfg.onStatus === 'function' ? cfg.onStatus : function () {};
    var onPreJoin = typeof cfg.onPreJoin === 'function' ? cfg.onPreJoin : function () {};
    var onJoined = typeof cfg.onJoined === 'function' ? cfg.onJoined : function () {};
    var onError = typeof cfg.onError === 'function' ? cfg.onError : function () {};
    var avatarBranding = cfg.avatarBranding || null;

    if (!sdk || !sdk.signature) {
      onError('SDK credentials missing.');
      return Promise.reject(new Error('SDK credentials missing.'));
    }

    function doJoin(passWord, useZak) {
      return new Promise(function (resolve, reject) {
        var finished = false;
        function finish(ok, val) {
          if (finished) return;
          finished = true;
          ok ? resolve(val) : reject(val);
        }

        var timer = window.setTimeout(function () {
          finish(false, new Error('Join timed out. If you see a pre-join screen, tap Join or allow camera/microphone.'));
        }, 120000);

        var statusHandler = function (data) {
          if (data && data.status === 2) {
            window.clearTimeout(timer);
            finish(true);
          } else if (data && data.status === 3) {
            window.clearTimeout(timer);
            finish(false, new Error('Disconnected from the Zoom meeting.'));
          }
        };

        if (typeof ZoomMtg.inMeetingServiceListener === 'function') {
          ZoomMtg.inMeetingServiceListener('onMeetingStatus', statusHandler);
        }

        var payload = {
          signature: sdk.signature,
          meetingNumber: String(sdk.meeting_number),
          userName: sdk.user_name || (isHost ? 'Host' : 'Guest'),
          passWord: passWord,
          success: function () {
            window.setTimeout(function () {
              window.clearTimeout(timer);
              finish(true);
            }, 2500);
          },
          error: function (err) {
            window.clearTimeout(timer);
            finish(false, new Error(errMsg(err)));
          }
        };

        var sdkKey = sdk.sdk_key || sdk.sdkKey || '';
        if (sdkKey) {
          payload.sdkKey = sdkKey;
        }
        if (sdk.user_email) {
          payload.userEmail = sdk.user_email;
        }
        if (useZak && isHost && sdk.zak) {
          payload.zak = sdk.zak;
        }

        ZoomMtg.join(payload);
      });
    }

    function joinMeeting() {
      var passwords = [];
      if (Array.isArray(sdk.password_candidates) && sdk.password_candidates.length) {
        sdk.password_candidates.forEach(function (p) {
          var v = String(p || '').trim();
          if (passwords.indexOf(v) === -1) passwords.push(v);
        });
      }
      var primary = String(sdk.password || '').trim();
      if (primary !== '' && passwords.indexOf(primary) === -1) passwords.unshift(primary);
      if (passwords.length === 0) passwords.push('');

      var zakModes = isHost && sdk.zak ? [true, false] : [false];
      var lastError = 'Join failed';
      var zi = 0;
      var pi = 0;
      var firstTry = true;

      function next(err) {
        if (err) {
          lastError = err && err.message ? err.message : lastError;
          if (!/password|passcode|wrong|zak/i.test(lastError)) {
            onError(lastError);
            return Promise.reject(err);
          }
        }
        if (zi >= zakModes.length) {
          onError(lastError);
          return Promise.reject(new Error(lastError));
        }
        if (pi >= passwords.length) {
          zi += 1;
          pi = 0;
          return next();
        }
        var pass = passwords[pi++];
        var useZak = zakModes[zi];
        onStatus(firstTry
          ? (isHost ? 'Joining as host…' : 'Joining meeting…')
          : 'Retrying connection…');
        firstTry = false;
        return doJoin(pass, useZak).catch(next);
      }

      return next();
    }

    onStatus('Loading Zoom components…');

    return loadZoomAssets(assetBase, meetingJs, zoomCssHref)
      .then(function () {
        onStatus('Preparing audio/video…');
        return prepareSdk(zoomLibUrl);
      })
      .then(function () {
        onStatus('Initializing meeting room…');
        showZoomRoot();
        if (avatarBranding) {
          startAvatarEnhancer(avatarBranding);
        }
        return initClient(leaveUrl);
      })
      .then(function () {
        if (isHost && !sdk.zak) {
          onStatus('Connecting as host…');
          return fetchHostZak().then(function (zak) {
            if (zak) sdk.zak = zak;
          });
        }
      })
      .then(function () {
        onPreJoin();
        return joinMeeting();
      })
      .then(function () {
        onJoined();
        document.body.classList.add('fm-meeting-active');
        if (!avatarObserver) {
          startAvatarEnhancer(avatarBranding);
        }
        window.setTimeout(function () {
          refreshParticipantLayout();
          enhanceVideoFrames(document.getElementById('zmmtg-root'));
        }, 400);
        window.setTimeout(function () {
          enhanceVideoFrames(document.getElementById('zmmtg-root'));
        }, 2000);
      })
      .catch(function (e) {
        onError(e && e.message ? e.message : String(e));
        throw e;
      });
  }

  global.FmZoomRoom = {
    errMsg: errMsg,
    loadZoomAssets: loadZoomAssets,
    prepareSdk: prepareSdk,
    initClient: initClient,
    startMeeting: startMeeting,
    stopShareWatch: stopShareWatch,
    applyPureShareLayout: applyPureShareLayout,
    refreshParticipantLayout: refreshParticipantLayout,
    showZoomRoot: showZoomRoot,
    startAvatarEnhancer: startAvatarEnhancer,
    stopAvatarEnhancer: stopAvatarEnhancer,
    getInitials: getInitials
  };
})(window);
