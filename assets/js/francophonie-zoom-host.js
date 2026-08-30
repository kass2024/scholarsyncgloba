/**
 * @deprecated Use francophonie-zoom-room.js (FmZoomRoom.startMeeting) instead.
 * Kept for backward compatibility — forwards to FmZoomRoom when available.
 */
(function (global) {
  global.startFrancophonieZoomHost = function (options) {
    if (global.FmZoomRoom && typeof global.FmZoomRoom.startMeeting === 'function') {
      return global.FmZoomRoom.startMeeting({
        sdk: options.sdk,
        leaveUrl: options.leaveUrl,
        zoomLibUrl: options.zoomLibUrl,
        assetBase: options.assetBase || '',
        meetingJs: options.meetingJs || '',
        zoomCssHref: options.zoomCssHref || '',
        isHost: true,
        onStatus: options.onStatus,
        onPreJoin: options.onPreJoin || function () {},
        onJoined: options.onJoined,
        onError: options.onError
      });
    }
    if (options.onError) {
      options.onError('Zoom loader missing. Hard-refresh (Ctrl+F5) and try again.');
    }
  };
})(window);
