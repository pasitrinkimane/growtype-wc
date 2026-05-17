/*
 * ATTENTION: An "eval-source-map" devtool has been used.
 * This devtool is neither made for production nor for readable output files.
 * It uses "eval()" calls to create a separate source file with attached SourceMaps in the browser devtools.
 * If you are trying to read the output file, select a different devtool (https://webpack.js.org/configuration/devtool/)
 * or disable the default devtool with "devtool: false".
 * If you are looking for production-ready output files, see mode: "production" (https://webpack.js.org/configuration/mode/).
 */
/******/ (function() { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./resources/scripts/wc-login.js":
/*!***************************************!*\
  !*** ./resources/scripts/wc-login.js ***!
  \***************************************/
/***/ (function() {

eval("(function ($) {\n  if (window.location.hash.length > 0) {\n    if (window.location.hash === jQuery('.u-column1 .e-register').attr('href')) {\n      jQuery('.u-column1').hide();\n      jQuery('.u-column2').fadeIn();\n    }\n  }\n  jQuery('.e-switchform').click(function () {\n    if (jQuery(this).closest('.u-column1').length > 0) {\n      jQuery(this).closest('.u-column1').fadeOut(function () {\n        jQuery('.u-column2').fadeIn();\n      });\n    } else {\n      jQuery(this).closest('.u-column2').fadeOut(function () {\n        jQuery('.u-column1').fadeIn();\n      });\n    }\n  });\n})(jQuery);//# sourceURL=[module]\n//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJuYW1lcyI6WyIkIiwid2luZG93IiwibG9jYXRpb24iLCJoYXNoIiwibGVuZ3RoIiwialF1ZXJ5IiwiYXR0ciIsImhpZGUiLCJmYWRlSW4iLCJjbGljayIsImNsb3Nlc3QiLCJmYWRlT3V0Il0sInNvdXJjZXMiOlsid2VicGFjazovL3NhZ2UvLi9yZXNvdXJjZXMvc2NyaXB0cy93Yy1sb2dpbi5qcz84MTAwIl0sInNvdXJjZXNDb250ZW50IjpbIihmdW5jdGlvbiAoJCkge1xuXG4gICAgaWYgKHdpbmRvdy5sb2NhdGlvbi5oYXNoLmxlbmd0aCA+IDApIHtcbiAgICAgICAgaWYgKHdpbmRvdy5sb2NhdGlvbi5oYXNoID09PSBqUXVlcnkoJy51LWNvbHVtbjEgLmUtcmVnaXN0ZXInKS5hdHRyKCdocmVmJykpIHtcbiAgICAgICAgICAgIGpRdWVyeSgnLnUtY29sdW1uMScpLmhpZGUoKTtcbiAgICAgICAgICAgIGpRdWVyeSgnLnUtY29sdW1uMicpLmZhZGVJbigpO1xuICAgICAgICB9XG4gICAgfVxuXG4gICAgalF1ZXJ5KCcuZS1zd2l0Y2hmb3JtJykuY2xpY2soZnVuY3Rpb24gKCkge1xuICAgICAgICBpZiAoalF1ZXJ5KHRoaXMpLmNsb3Nlc3QoJy51LWNvbHVtbjEnKS5sZW5ndGggPiAwKSB7XG4gICAgICAgICAgICBqUXVlcnkodGhpcykuY2xvc2VzdCgnLnUtY29sdW1uMScpLmZhZGVPdXQoZnVuY3Rpb24gKCkge1xuICAgICAgICAgICAgICAgIGpRdWVyeSgnLnUtY29sdW1uMicpLmZhZGVJbigpO1xuICAgICAgICAgICAgfSlcbiAgICAgICAgfSBlbHNlIHtcbiAgICAgICAgICAgIGpRdWVyeSh0aGlzKS5jbG9zZXN0KCcudS1jb2x1bW4yJykuZmFkZU91dChmdW5jdGlvbiAoKSB7XG4gICAgICAgICAgICAgICAgalF1ZXJ5KCcudS1jb2x1bW4xJykuZmFkZUluKCk7XG4gICAgICAgICAgICB9KVxuICAgICAgICB9XG4gICAgfSk7XG5cbn0pKGpRdWVyeSk7XG4iXSwibWFwcGluZ3MiOiJBQUFBLENBQUMsVUFBVUEsQ0FBQyxFQUFFO0VBRVYsSUFBSUMsTUFBTSxDQUFDQyxRQUFRLENBQUNDLElBQUksQ0FBQ0MsTUFBTSxHQUFHLENBQUMsRUFBRTtJQUNqQyxJQUFJSCxNQUFNLENBQUNDLFFBQVEsQ0FBQ0MsSUFBSSxLQUFLRSxNQUFNLENBQUMsd0JBQXdCLENBQUMsQ0FBQ0MsSUFBSSxDQUFDLE1BQU0sQ0FBQyxFQUFFO01BQ3hFRCxNQUFNLENBQUMsWUFBWSxDQUFDLENBQUNFLElBQUksQ0FBQyxDQUFDO01BQzNCRixNQUFNLENBQUMsWUFBWSxDQUFDLENBQUNHLE1BQU0sQ0FBQyxDQUFDO0lBQ2pDO0VBQ0o7RUFFQUgsTUFBTSxDQUFDLGVBQWUsQ0FBQyxDQUFDSSxLQUFLLENBQUMsWUFBWTtJQUN0QyxJQUFJSixNQUFNLENBQUMsSUFBSSxDQUFDLENBQUNLLE9BQU8sQ0FBQyxZQUFZLENBQUMsQ0FBQ04sTUFBTSxHQUFHLENBQUMsRUFBRTtNQUMvQ0MsTUFBTSxDQUFDLElBQUksQ0FBQyxDQUFDSyxPQUFPLENBQUMsWUFBWSxDQUFDLENBQUNDLE9BQU8sQ0FBQyxZQUFZO1FBQ25ETixNQUFNLENBQUMsWUFBWSxDQUFDLENBQUNHLE1BQU0sQ0FBQyxDQUFDO01BQ2pDLENBQUMsQ0FBQztJQUNOLENBQUMsTUFBTTtNQUNISCxNQUFNLENBQUMsSUFBSSxDQUFDLENBQUNLLE9BQU8sQ0FBQyxZQUFZLENBQUMsQ0FBQ0MsT0FBTyxDQUFDLFlBQVk7UUFDbkROLE1BQU0sQ0FBQyxZQUFZLENBQUMsQ0FBQ0csTUFBTSxDQUFDLENBQUM7TUFDakMsQ0FBQyxDQUFDO0lBQ047RUFDSixDQUFDLENBQUM7QUFFTixDQUFDLEVBQUVILE1BQU0sQ0FBQyIsImZpbGUiOiIuL3Jlc291cmNlcy9zY3JpcHRzL3djLWxvZ2luLmpzIiwic291cmNlUm9vdCI6IiJ9\n//# sourceURL=webpack-internal:///./resources/scripts/wc-login.js\n");

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module can't be inlined because the eval-source-map devtool is used.
/******/ 	var __webpack_exports__ = {};
/******/ 	__webpack_modules__["./resources/scripts/wc-login.js"]();
/******/ 	
/******/ })()
;