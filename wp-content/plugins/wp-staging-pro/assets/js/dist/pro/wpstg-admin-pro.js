(function () {
  'use strict';

  function asyncGeneratorStep(gen, resolve, reject, _next, _throw, key, arg) {
    try {
      var info = gen[key](arg);
      var value = info.value;
    } catch (error) {
      reject(error);
      return;
    }

    if (info.done) {
      resolve(value);
    } else {
      Promise.resolve(value).then(_next, _throw);
    }
  }

  function _asyncToGenerator(fn) {
    return function () {
      var self = this,
          args = arguments;
      return new Promise(function (resolve, reject) {
        var gen = fn.apply(self, args);

        function _next(value) {
          asyncGeneratorStep(gen, resolve, reject, _next, _throw, "next", value);
        }

        function _throw(err) {
          asyncGeneratorStep(gen, resolve, reject, _next, _throw, "throw", err);
        }

        _next(undefined);
      });
    };
  }

  function _unsupportedIterableToArray(o, minLen) {
    if (!o) return;
    if (typeof o === "string") return _arrayLikeToArray(o, minLen);
    var n = Object.prototype.toString.call(o).slice(8, -1);
    if (n === "Object" && o.constructor) n = o.constructor.name;
    if (n === "Map" || n === "Set") return Array.from(o);
    if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen);
  }

  function _arrayLikeToArray(arr, len) {
    if (len == null || len > arr.length) len = arr.length;

    for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i];

    return arr2;
  }

  function _createForOfIteratorHelperLoose(o, allowArrayLike) {
    var it;

    if (typeof Symbol === "undefined" || o[Symbol.iterator] == null) {
      if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") {
        if (it) o = it;
        var i = 0;
        return function () {
          if (i >= o.length) return {
            done: true
          };
          return {
            done: false,
            value: o[i++]
          };
        };
      }

      throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.");
    }

    it = o[Symbol.iterator]();
    return it.next.bind(it);
  }

  /**
   * Polyfills the `Element.prototype.closest` function if not available in the browser.
   *
   * @return {Function} A function that will return the closest element, by selector, to this element.
   */
  function polyfillClosest() {
    if (Element.prototype.closest) {
      if (!Element.prototype.matches) {
        Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
      }

      Element.prototype.closest = function (s) {
        var el = this;

        do {
          if (Element.prototype.matches.call(el, s)) return el;
          el = el.parentElement || el.parentNode;
        } while (el !== null && el.nodeType === 1);

        return null;
      };
    }

    return function (element, selector) {
      return element instanceof Element ? element.closest(selector) : null;
    };
  }

  polyfillClosest();

  /**
   * WP STAGING basic jQuery replacement
   */

  /**
   * Shortcut for document.querySelector() or jQuery's $()
   * Return single element only
   */

  function qs(selector) {
    return document.querySelector(selector);
  }
  /**
   * alternative of jQuery - $(parent).on(event, selector, handler)
   */

  function addEvent(parent, evt, selector, handler) {
    if (!parent instanceof Element) {
      return;
    }

    parent.addEventListener(evt, function (event) {
      if (event.target.matches(selector + ', ' + selector + ' *')) {
        handler(event.target.closest(selector), event);
      }
    }, false);
  }
  function fadeOut(element, duration) {
    if (duration === void 0) {
      duration = 300;
    }

    element.style.opacity = 1;
    element.style.transitionProperty = 'opacity';
    element.style.transitionDuration = duration + 'ms';
    setTimeout(function () {
      element.style.opacity = 0;
      window.setTimeout(function () {
        element.style.display = 'none';
        element.style.removeProperty('opacity');
        element.style.removeProperty('transition-duration');
        element.style.removeProperty('transition-property');
      }, duration);
    }, 0);
  }
  function getParents(element, selector) {
    var result = [];

    for (var parent = element && element.parentElement; parent; parent = parent.parentElement) {
      if (parent.matches(selector)) {
        result.push(parent);
      }
    }

    return result;
  }

  // This is to make sure we have not many ajax request even when they were not required i.e. while typing.

  var DELAY_TIME_DB_CHECK = 300;

  var WpstgCloneEdit = /*#__PURE__*/function () {
    function WpstgCloneEdit(workflowSelector, dbCheckTriggerClass, wpstgObject, databaseCheckAction) {
      if (workflowSelector === void 0) {
        workflowSelector = '#wpstg-workflow';
      }

      if (dbCheckTriggerClass === void 0) {
        dbCheckTriggerClass = '.wpstg-edit-clone-db-inputs';
      }

      if (wpstgObject === void 0) {
        wpstgObject = wpstg;
      }

      if (databaseCheckAction === void 0) {
        databaseCheckAction = 'wpstg_database_connect';
      }

      this.workflow = qs(workflowSelector);
      this.dbCheckTriggerClass = dbCheckTriggerClass;
      this.wpstgObject = wpstgObject;
      this.databaseCheckAction = databaseCheckAction;
      this.dbCheckTimer = null;
      this.abortDbCheckController = null;
      this.dbCheckCallStatus = false;
      this.notyf = new Notyf({
        duration: 10000,
        position: {
          x: 'center',
          y: 'bottom'
        },
        dismissible: true,
        types: [{
          type: 'warning',
          background: 'orange',
          icon: false
        }]
      });
      this.init();
    }

    var _proto = WpstgCloneEdit.prototype;

    _proto.addEvents = function addEvents() {
      var _this = this;

      // early bail if workflow object not available.
      if (this.workflow === null) {
        return;
      }

      ['paste', 'input'].forEach(function (evt) {
        addEvent(_this.workflow, evt, _this.dbCheckTriggerClass, function () {
          // abort previous database check call if it was running
          if (_this.dbCheckCallStatus === true) {
            _this.abortDbCheckController.abort();

            _this.abortDbCheckController = null;
            _this.dbCheckCallStatus = false;
          } // check for db connection after specific delay but reset the timer if these event occur again


          clearTimeout(_this.dbCheckTimer);
          _this.dbCheckTimer = setTimeout(function () {
            _this.checkDatabase();
          }, DELAY_TIME_DB_CHECK);
        });
      });
    };

    _proto.init = function init() {
      this.addEvents();
    };

    _proto.checkDatabase = function checkDatabase() {
      var _this2 = this;

      var idPrefix = '#wpstg-edit-clone-data-';
      var externalDBUser = qs(idPrefix + 'database-user').value;
      var externalDBPassword = qs(idPrefix + 'database-password').value;
      var externalDBDatabase = qs(idPrefix + 'database-database').value;
      var externalDBHost = qs(idPrefix + 'database-server').value;
      var externalDBPrefix = qs(idPrefix + 'database-prefix').value;

      if (externalDBUser === '' && externalDBPassword === '' && externalDBDatabase === '' && externalDBPrefix === '') {
        qs('#wpstg-save-clone-data').disabled = false;
        return;
      }

      this.abortDbCheckController = new AbortController();
      this.dbCheckCallStatus = true;
      fetch(this.wpstgObject.ajaxUrl, {
        method: 'POST',
        signal: this.abortDbCheckController.signal,
        credentials: 'same-origin',
        body: new URLSearchParams({
          action: this.databaseCheckAction,
          accessToken: this.wpstgObject.accessToken,
          nonce: this.wpstgObject.nonce,
          databaseUser: externalDBUser,
          databasePassword: externalDBPassword,
          databaseServer: externalDBHost,
          databaseDatabase: externalDBDatabase,
          databasePrefix: externalDBPrefix,
          databaseEnsurePrefixTableExist: true
        }),
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        }
      }).then(function (response) {
        _this2.dbCheckCallStatus = false;

        if (response.ok) {
          return response.json();
        }

        return Promise.reject(response);
      }).then(function (data) {
        // dismiss previous toasts
        _this2.notyf.dismissAll(); // failed request


        if (false === data) {
          _this2.notyf.error(_this2.wpstgObject.i18n['dbConnectionFailed']);

          qs('#wpstg-save-clone-data').disabled = true;
          return;
        } // failed db connection


        if ('undefined' !== typeof data.errors && data.errors && 'undefined' !== typeof data.success && data.success === 'false') {
          _this2.notyf.error(_this2.wpstgObject.i18n['dbConnectionFailed'] + '! <br/> Error: ' + data.errors);

          qs('#wpstg-save-clone-data').disabled = true;
          return;
        } // prefix warning


        if ('undefined' !== typeof data.errors && data.errors && 'undefined' !== typeof data.success && data.success === 'true') {
          _this2.notyf.open({
            type: 'warning',
            message: 'Warning: ' + data.errors
          });

          qs('#wpstg-save-clone-data').disabled = true;
          return;
        } // db connection successful


        if ('undefined' !== typeof data.success && data.success) {
          _this2.notyf.success(_this2.wpstgObject.i18n['dbConnectionSuccess']);

          qs('#wpstg-save-clone-data').disabled = false;
        }
      })["catch"](function (error) {
        _this2.dbCheckCallStatus = false;
        console.warn(_this2.wpstgObject.i18n['somethingWentWrong'], error);
        qs('#wpstg-save-clone-data').disabled = true;
      });
    };

    return WpstgCloneEdit;
  }();

  /**
   * Push Table Selection
   */

  var WpstgPushTableSelection = /*#__PURE__*/function () {
    function WpstgPushTableSelection(workflowSelector, inputSelector, wpstgObject) {
      if (workflowSelector === void 0) {
        workflowSelector = '#wpstg-workflow';
      }

      if (inputSelector === void 0) {
        inputSelector = '#wpstg_select_tables_pushing';
      }

      if (wpstgObject === void 0) {
        wpstgObject = wpstg;
      }

      this.workflow = qs(workflowSelector);
      this.inputSelector = inputSelector;
      this.input = qs(inputSelector);
      this.wpstgObject = wpstgObject;
      this.cloneID = this.input.getAttribute('data-clone');
      this.tablePrefix = this.input.getAttribute('data-prefix');
      this.isNetwork = this.input.getAttribute('data-network') === 'true';
      this.areAllTablesChecked = true;
      this.init();
    }

    var _proto = WpstgPushTableSelection.prototype;

    _proto.addEvents = function addEvents() {
      var _this = this;

      addEvent(document.body, 'click', '.wpstg-button-show-tables', function (target) {
        _this.showAllTables();
      });
      addEvent(document.body, 'click', '.wpstg-button-db-prefix', function () {
        _this.selectDefaultTables();
      });
      addEvent(document.body, 'click', '.wpstg-button-unselect', function () {
        _this.toggleTableSelection();
      });
      addEvent(document.body, 'change', this.inputSelector, function () {
        _this.countSelectedTables();
      });
    };

    _proto.init = function init() {
      this.addEvents();
    };

    _proto.showAllTables = function showAllTables() {
      var _this2 = this;

      var includedTables = this.getIncludedTables();
      var excludedTables = this.getExcludedTables();

      if (includedTables.length > excludedTables.length) {
        includedTables = '';
      } else if (excludedTables.length > includedTables.length) {
        excludedTables = '';
      }

      fetch(this.wpstgObject.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: new URLSearchParams({
          action: 'wpstg_push_tables',
          accessToken: this.wpstgObject.accessToken,
          nonce: this.wpstgObject.nonce,
          clone: this.cloneID,
          includedTables: includedTables,
          excludedTables: excludedTables,
          selectedTablesWithoutPrefix: this.getSelectedTablesWithoutPrefix()
        }),
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        }
      }).then(function (response) {
        if (response.ok) {
          return response.json();
        }

        return Promise.reject(response);
      }).then(function (data) {
        // Reload current page if successful.
        if ('undefined' !== typeof data.success && data.success) {
          _this2.input.innerHTML = data.content;
          fadeOut(qs('.wpstg-button-show-tables'), 300);

          _this2.countSelectedTables();

          return;
        } // There will be message probably in case of error


        if ('undefined' !== typeof data.message) {
          _this2.notyf.error(data.message);

          return;
        }

        _this2.notyf.error(_this2.wpstgObject.i18n['somethingWentWrong']);
      })["catch"](function (error) {
        console.warn(_this2.wpstgObject.i18n['somethingWentWrong'], error);
      });
    };

    _proto.getRegexPattern = function getRegexPattern() {
      var pattern = '^' + this.tablePrefix;

      if (this.wpstgObject.isMultisite === '1' && !this.isNetwork) {
        pattern += '([^0-9])_*';
      }

      return pattern;
    };

    _proto.selectDefaultTables = function selectDefaultTables() {
      var options = this.input.querySelectorAll('.wpstg-db-table');
      var pattern = this.getRegexPattern();
      options.forEach(function (option) {
        var name = option.getAttribute('name', '');

        if (name.match(pattern)) {
          option.selected = true;
        } else {
          option.selected = false;
        }
      });
      this.countSelectedTables();
    };

    _proto.toggleTableSelection = function toggleTableSelection() {
      if (false === this.areAllTablesChecked) {
        this.input.querySelectorAll('.wpstg-db-table').forEach(function (option) {
          option.selected = true;
        });
        this.workflow.querySelector('.wpstg-button-unselect').innerHTML = 'Unselect All';
        this.areAllTablesChecked = true;
      } else {
        this.input.querySelectorAll('.wpstg-db-table').forEach(function (option) {
          option.selected = false;
        });
        this.workflow.querySelector('.wpstg-button-unselect').innerHTML = 'Select All';
        this.areAllTablesChecked = false;
      }

      this.countSelectedTables();
    };

    _proto.getSelectedTablesWithoutPrefix = function getSelectedTablesWithoutPrefix() {
      var selectedTablesWithoutPrefix = [];
      var regexPattern = this.getRegexPattern();
      this.input.querySelectorAll('option:checked').forEach(function (option) {
        var name = option.getAttribute('name', '');

        if (!name.match(regexPattern)) {
          selectedTablesWithoutPrefix.push(name);
        }
      });
      return selectedTablesWithoutPrefix.join(this.wpstgObject.settings.directorySeparator);
    };

    _proto.getIncludedTables = function getIncludedTables() {
      var tables = [];
      var regexPattern = this.getRegexPattern();
      this.input.querySelectorAll('option:checked').forEach(function (option) {
        var name = option.getAttribute('name', '');

        if (name.match(regexPattern)) {
          tables.push(name);
        }
      });
      return tables.join(this.wpstgObject.settings.directorySeparator);
    };

    _proto.getExcludedTables = function getExcludedTables() {
      var tables = [];
      var regexPattern = this.getRegexPattern();
      this.input.querySelectorAll('option:not(:checked)').forEach(function (option) {
        var name = option.getAttribute('name', '');

        if (name.match(regexPattern)) {
          tables.push(name);
        }
      });
      return tables.join(this.wpstgObject.settings.directorySeparator);
    };

    _proto.countSelectedTables = function countSelectedTables() {
      var tablesCount = this.input.querySelectorAll('option:checked').length;
      var tablesCountElement = qs('#wpstg-tables-count');

      if (tablesCount === 0) {
        tablesCountElement.classList.add('danger');
        tablesCountElement.innerHTML = this.wpstgObject.i18n['noTableSelected'];
      } else {
        tablesCountElement.classList.remove('danger');
        tablesCountElement.innerHTML = this.wpstgObject.i18n['tablesSelected'].replace('{d}', tablesCount);
      }
    };

    return WpstgPushTableSelection;
  }();

  /**
   * Push File Selection
   */

  var WpstgPushFileSelection = /*#__PURE__*/function () {
    function WpstgPushFileSelection(workflowSelector, filesTabSelector, dirSelector, wpstgObject) {
      if (workflowSelector === void 0) {
        workflowSelector = '#wpstg-workflow';
      }

      if (filesTabSelector === void 0) {
        filesTabSelector = '#wpstg-scanning-files';
      }

      if (dirSelector === void 0) {
        dirSelector = '.wpstg-check-dir';
      }

      if (wpstgObject === void 0) {
        wpstgObject = wpstg;
      }

      this.workflow = qs(workflowSelector);
      this.filesTabSelector = filesTabSelector;
      this.filesTab = qs(filesTabSelector);
      this.dirSelector = dirSelector;
      this.wpstgObject = wpstgObject;
      this.init();
    }

    var _proto = WpstgPushFileSelection.prototype;

    _proto.addEvents = function addEvents() {
      var _this = this;

      addEvent(this.workflow, 'change', this.filesTabSelector + ' ' + this.dirSelector, function (el) {
        _this.toggleChildren(el);

        _this.countSelectedFiles();
      });
    };

    _proto.init = function init() {
      this.addEvents();
    };

    _proto.toggleChildren = function toggleChildren(el) {
      var parent = getParents(el, '.wpstg-dir')[0];
      var checkboxes = parent.querySelectorAll('.wpstg-subdir>.wpstg-dir>' + this.dirSelector);
      checkboxes.forEach(function (cb) {
        cb.checked = el.checked;
      });
    };

    _proto.countSelectedFiles = function countSelectedFiles() {
      var _this2 = this;

      var filesCount = this.filesTab.querySelectorAll(this.dirSelector + ':checked').length;
      var filesCountElement = qs('#wpstg-files-count');

      if (filesCount === 0) {
        filesCountElement.classList.add('danger');
        filesCountElement.innerHTML = this.wpstgObject.i18n['noFileSelected'];
      } else {
        var themesCount = 0;
        var pluginsCount = 0;
        filesCountElement.classList.remove('danger');
        this.filesTab.querySelectorAll('.wpstg-dir:not(.wpstg-sub-dir)>.wpstg-expand-dirs').forEach(function (head) {
          if (head.innerHTML === 'plugins') {
            pluginsCount = head.nextSibling.querySelectorAll(_this2.dirSelector + ':checked').length;
          }

          if (head.innerHTML === 'themes') {
            themesCount = head.nextSibling.querySelectorAll(_this2.dirSelector + ':checked').length;
          }
        });
        filesCountElement.innerHTML = this.wpstgObject.i18n['filesSelected'].replace('{t}', themesCount).replace('{p}', pluginsCount);
      }
    };

    return WpstgPushFileSelection;
  }();

  /**
   * This is a namespaced port of https://github.com/tristen/hoverintent,
   * with slight modification to accept selector with dynamically added element in dom,
   * instead of just already present element.
   *
   * @param {HTMLElement} parent
   * @param {string} selector
   * @param {CallableFunction} onOver
   * @param {CallableFunction} onOut
   *
   * @return {object}
   */

  function wpstgHoverIntent (parent, selector, onOver, onOut) {
    var x;
    var y;
    var pX;
    var pY;
    var mouseOver = false;
    var focused = false;
    var h = {};
    var state = 0;
    var timer = 0;
    var options = {
      sensitivity: 7,
      interval: 100,
      timeout: 0,
      handleFocus: false
    };

    function delay(el, e) {
      if (timer) {
        timer = clearTimeout(timer);
      }

      state = 0;
      return focused ? undefined : onOut(el, e);
    }

    function tracker(e) {
      x = e.clientX;
      y = e.clientY;
    }

    function compare(el, e) {
      if (timer) timer = clearTimeout(timer);

      if (Math.abs(pX - x) + Math.abs(pY - y) < options.sensitivity) {
        state = 1;
        return focused ? undefined : onOver(el, e);
      } else {
        pX = x;
        pY = y;
        timer = setTimeout(function () {
          compare(el, e);
        }, options.interval);
      }
    } // Public methods


    h.options = function (opt) {
      var focusOptionChanged = opt.handleFocus !== options.handleFocus;
      options = Object.assign({}, options, opt);

      if (focusOptionChanged) {
        options.handleFocus ? addFocus() : removeFocus();
      }

      return h;
    };

    function dispatchOver(el, e) {
      mouseOver = true;

      if (timer) {
        timer = clearTimeout(timer);
      }

      el.removeEventListener('mousemove', tracker, false);

      if (state !== 1) {
        pX = e.clientX;
        pY = e.clientY;
        el.addEventListener('mousemove', tracker, false);
        timer = setTimeout(function () {
          compare(el, e);
        }, options.interval);
      }

      return this;
    }
    /**
     * Newly added method,
     * A wrapper around dispatchOver to support dynamically added elements to dom
     */


    function onMouseOver(event) {
      if (event.target.matches(selector + ', ' + selector + ' *')) {
        dispatchOver(event.target.closest(selector), event);
      }
    }

    function dispatchOut(el, e) {
      mouseOver = false;

      if (timer) {
        timer = clearTimeout(timer);
      }

      el.removeEventListener('mousemove', tracker, false);

      if (state === 1) {
        timer = setTimeout(function () {
          delay(el, e);
        }, options.timeout);
      }

      return this;
    }
    /**
     * Newly added method,
     * A wrapper around dispatchOut to support dynamically added elements to dom
     */


    function onMouseOut(event) {
      if (event.target.matches(selector + ', ' + selector + ' *')) {
        dispatchOut(event.target.closest(selector), event);
      }
    }

    function dispatchFocus(el, e) {
      if (!mouseOver) {
        focused = true;
        onOver(el, e);
      }
    }
    /**
     * Newly added method,
     * A wrapper around dispatchFocus to support dynamically added elements to dom
     */


    function onFocus(event) {
      if (event.target.matches(selector + ', ' + selector + ' *')) {
        dispatchFocus(event.target.closest(selector), event);
      }
    }

    function dispatchBlur(el, e) {
      if (!mouseOver && focused) {
        focused = false;
        onOut(el, e);
      }
    }
    /**
     * Newly added method,
     * A wrapper around dispatchBlur to support dynamically added elements to dom
     */


    function onBlur(event) {
      if (event.target.matches(selector + ', ' + selector + ' *')) {
        dispatchBlur(event.target.closest(selector), event);
      }
    }
    /**
     * Modified to support dynamically added element
     */

    function addFocus() {
      parent.addEventListener('focus', onFocus, false);
      parent.addEventListener('blur', onBlur, false);
    }
    /**
     * Modified to support dynamically added element
     */


    function removeFocus() {
      parent.removeEventListener('focus', onFocus, false);
      parent.removeEventListener('blur', onBlur, false);
    }
    /**
     * Modified to support dynamically added element
     */


    h.remove = function () {
      if (!parent) {
        return;
      }

      parent.removeEventListener('mouseover', onMouseOver, false);
      parent.removeEventListener('mouseout', onMouseOut, false);
      removeFocus();
    };
    /**
     * Modified to support dynamically added element
     */


    if (parent) {
      parent.addEventListener('mouseover', onMouseOver, false);
      parent.addEventListener('mouseout', onMouseOut, false);
    }

    return h;
  }

  var WPStagingCommon = (function ($) {
    var WPStagingCommon = {
      continueErrorHandle: true,
      cache: {
        elements: [],
        get: function get(selector) {
          // It is already cached!
          if ($.inArray(selector, this.elements) !== -1) {
            return this.elements[selector];
          } // Create cache and return


          this.elements[selector] = $(selector);
          return this.elements[selector];
        },
        refresh: function refresh(selector) {
          selector.elements[selector] = $(selector);
        }
      },
      setJobId: function setJobId(jobId) {
        localStorage.setItem('jobIdBeingProcessed', jobId);
      },
      getJobId: function getJobId() {
        return localStorage.getItem('jobIdBeingProcessed');
      },
      listenTooltip: function listenTooltip() {
        wpstgHoverIntent(document, '.wpstg--tooltip', function (target, event) {
          target.querySelector('.wpstg--tooltiptext').style.visibility = 'visible';
        }, function (target, event) {
          target.querySelector('.wpstg--tooltiptext').style.visibility = 'hidden';
        });
      },
      isEmpty: function isEmpty(obj) {
        for (var prop in obj) {
          if (obj.hasOwnProperty(prop)) {
            return false;
          }
        }

        return true;
      },
      // Get the custom themed Swal Modal for WP Staging
      // Easy to maintain now in one place now
      getSwalModal: function getSwalModal(isContentCentered, customClasses) {
        if (isContentCentered === void 0) {
          isContentCentered = false;
        }

        if (customClasses === void 0) {
          customClasses = {};
        }

        // common style for all swal modal used in WP Staging
        var defaultCustomClasses = {
          confirmButton: 'wpstg--btn--confirm wpstg-blue-primary wpstg-button wpstg-link-btn wpstg-100-width',
          cancelButton: 'wpstg--btn--cancel wpstg-blue-primary wpstg-link-btn wpstg-100-width',
          actions: 'wpstg--modal--actions',
          popup: isContentCentered ? 'wpstg-swal-popup centered-modal' : 'wpstg-swal-popup'
        }; // If a attribute exists in both default and additional attributes,
        // The class(es) of the additional attribute will overrite the default one.

        var options = {
          customClass: Object.assign(defaultCustomClasses, customClasses),
          buttonsStyling: false,
          reverseButtons: true,
          showClass: {
            popup: 'wpstg--swal2-show wpstg-swal-show'
          }
        };
        return wpstgSwal.mixin(options);
      },
      showSuccessModal: function showSuccessModal(htmlContent) {
        this.getSwalModal().fire({
          showConfirmButton: false,
          showCancelButton: true,
          cancelButtonText: 'OK',
          icon: 'success',
          title: 'Success!',
          html: '<div class="wpstg--grey" style="text-align: left; margin-top: 8px;">' + htmlContent + '</div>'
        });
      },
      showWarningModal: function showWarningModal(htmlContent) {
        this.getSwalModal().fire({
          showConfirmButton: false,
          showCancelButton: true,
          cancelButtonText: 'OK',
          icon: 'warning',
          title: '',
          html: '<div class="wpstg--grey" style="text-align: left; margin-top: 8px;">' + htmlContent + '</div>'
        });
      },
      showErrorModal: function showErrorModal(htmlContent) {
        this.getSwalModal().fire({
          showConfirmButton: false,
          showCancelButton: true,
          cancelButtonText: 'OK',
          icon: 'error',
          title: 'Error!',
          html: '<div class="wpstg--grey" style="text-align: left; margin-top: 8px;">' + htmlContent + '</div>'
        });
      },
      getSwalContainer: function getSwalContainer() {
        return wpstgSwal.getContainer();
      },
      closeSwalModal: function closeSwalModal() {
        wpstgSwal.close();
      },

      /**
       * Treats a default response object generated by WordPress's
       * wp_send_json_success() or wp_send_json_error() functions in
       * PHP, parses it in JavaScript, and either throws if it's an error,
       * or returns the data if the response is successful.
       *
       * @param {object} response
       * @return {*}
       */
      getDataFromWordPressResponse: function getDataFromWordPressResponse(response) {
        if (typeof response !== 'object') {
          throw new Error('Unexpected response (ERR 1341)');
        }

        if (!response.hasOwnProperty('success')) {
          throw new Error('Unexpected response (ERR 1342)');
        }

        if (!response.hasOwnProperty('data')) {
          throw new Error('Unexpected response (ERR 1343)');
        }

        if (response.success === false) {
          if (response.data instanceof Array && response.data.length > 0) {
            throw new Error(response.data.shift());
          } else {
            throw new Error('Response was not successful');
          }
        } else {
          // Successful response. Return the data.
          return response.data;
        }
      },
      isLoading: function isLoading(_isLoading) {
        if (!_isLoading || _isLoading === false) {
          WPStagingCommon.cache.get('.wpstg-loader').hide();
        } else {
          WPStagingCommon.cache.get('.wpstg-loader').show();
        }
      },

      /**
       * Convert the given url to make it slug compatible
       * @param {string} url
       * @return {string}
       */
      slugify: function slugify(url) {
        return url.toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s+/g, '-').replace(/&/g, '-and-').replace(/[^a-z0-9\-]/g, '').replace(/-+/g, '-').replace(/^-*/, '').replace(/-*$/, '');
      },
      showAjaxFatalError: function showAjaxFatalError(response, prependMessage, appendMessage) {
        prependMessage = prependMessage ? prependMessage + '<br/><br/>' : 'Something went wrong! <br/><br/>';
        appendMessage = appendMessage ? appendMessage + '<br/><br/>' : '<br/><br/>Please try the <a href=\'https://wp-staging.com/docs/wp-staging-settings-for-small-servers/\' target=\'_blank\'>WP Staging Small Server Settings</a> or submit an error report and contact us.';

        if (response === false) {
          WPStagingCommon.showError(prependMessage + ' Error: No response.' + appendMessage);
          window.removeEventListener('beforeunload', WPStaging.warnIfClosingDuringProcess);
          return;
        }

        if (typeof response.error !== 'undefined' && response.error) {
          WPStagingCommon.showError(prependMessage + ' Error: ' + response.message + appendMessage);
          window.removeEventListener('beforeunload', WPStaging.warnIfClosingDuringProcess);
          return;
        }
      },
      handleFetchErrors: function handleFetchErrors(response) {
        if (!response.ok) {
          WPStagingCommon.showError('Error: ' + response.status + ' - ' + response.statusText + '. Please try again or contact support.');
        }

        return response;
      },
      showError: function showError(message) {
        WPStagingCommon.cache.get('#wpstg-try-again').css('display', 'inline-block');
        WPStagingCommon.cache.get('#wpstg-cancel-cloning').text('Reset');
        WPStagingCommon.cache.get('#wpstg-resume-cloning').show();
        WPStagingCommon.cache.get('#wpstg-error-wrapper').show();
        WPStagingCommon.cache.get('#wpstg-error-details').show().html(message);
        WPStagingCommon.cache.get('#wpstg-removing-clone').removeClass('loading');
        WPStagingCommon.cache.get('.wpstg-loader').hide();
        $('.wpstg--modal--process--generic-problem').show().html(message);
      },
      resetErrors: function resetErrors() {
        WPStagingCommon.cache.get('#wpstg-error-details').hide().html('');
      },

      /**
       * Ajax Requests
       * @param {Object} data
       * @param {Function} callback
       * @param {string} dataType
       * @param {bool} showErrors
       * @param {int} tryCount
       * @param {float} incrementRatio
       * @param {function} errorCallback
       */
      ajax: function ajax(data, callback, dataType, showErrors, tryCount, incrementRatio, errorCallback) {
        if (incrementRatio === void 0) {
          incrementRatio = null;
        }

        if (errorCallback === void 0) {
          errorCallback = null;
        }

        if ('undefined' === typeof dataType) {
          dataType = 'json';
        }

        if (false !== showErrors) {
          showErrors = true;
        }

        tryCount = 'undefined' === typeof tryCount ? 0 : tryCount;
        var retryLimit = 10;
        var retryTimeout = 10000 * tryCount;
        incrementRatio = parseInt(incrementRatio);

        if (!isNaN(incrementRatio)) {
          retryTimeout *= incrementRatio;
        }

        $.ajax({
          url: ajaxurl + '?action=wpstg_processing&_=' + Date.now() / 1000,
          type: 'POST',
          dataType: dataType,
          cache: false,
          data: data,
          error: function error(xhr, textStatus, errorThrown) {
            console.log(xhr.status + ' ' + xhr.statusText + '---' + textStatus);

            if (typeof errorCallback === 'function') {
              // Custom error handler
              errorCallback(xhr, textStatus, errorThrown);

              if (!WPStagingCommon.continueErrorHandle) {
                // Reset state
                WPStagingCommon.continueErrorHandle = true;
                return;
              }
            } // Default error handler


            tryCount++;

            if (tryCount <= retryLimit) {
              setTimeout(function () {
                WPStagingCommon.ajax(data, callback, dataType, showErrors, tryCount, incrementRatio);
                return;
              }, retryTimeout);
            } else {
              var errorCode = 'undefined' === typeof xhr.status ? 'Unknown' : xhr.status;
              WPStagingCommon.showError('Fatal Error:  ' + errorCode + ' Please try the <a href=\'https://wp-staging.com/docs/wp-staging-settings-for-small-servers/\' target=\'_blank\'>WP Staging Small Server Settings</a> or submit an error report and contact us.');
            }
          },
          success: function success(data) {
            if ('function' === typeof callback) {
              callback(data);
            }
          },
          statusCode: {
            404: function _() {
              if (tryCount >= retryLimit) {
                WPStagingCommon.showError('Error 404 - Can\'t find ajax request URL! Please try the <a href=\'https://wp-staging.com/docs/wp-staging-settings-for-small-servers/\' target=\'_blank\'>WP Staging Small Server Settings</a> or submit an error report and contact us.');
              }
            },
            500: function _() {
              if (tryCount >= retryLimit) {
                WPStagingCommon.showError('Fatal Error 500 - Internal server error while processing the request! Please try the <a href=\'https://wp-staging.com/docs/wp-staging-settings-for-small-servers/\' target=\'_blank\'>WP Staging Small Server Settings</a> or submit an error report and contact us.');
              }
            },
            504: function _() {
              if (tryCount > retryLimit) {
                WPStagingCommon.showError('Error 504 - It looks like your server is rate limiting ajax requests. Please try to resume after a minute. If this still not works try the <a href=\'https://wp-staging.com/docs/wp-staging-settings-for-small-servers/\' target=\'_blank\'>WP Staging Small Server Settings</a> or submit an error report and contact us.\n\ ');
              }
            },
            502: function _() {
              if (tryCount >= retryLimit) {
                WPStagingCommon.showError('Error 502 - It looks like your server is rate limiting ajax requests. Please try to resume after a minute. If this still not works try the <a href=\'https://wp-staging.com/docs/wp-staging-settings-for-small-servers/\' target=\'_blank\'>WP Staging Small Server Settings</a> or submit an error report and contact us.\n\ ');
              }
            },
            503: function _() {
              if (tryCount >= retryLimit) {
                WPStagingCommon.showError('Error 503 - It looks like your server is rate limiting ajax requests. Please try to resume after a minute. If this still not works try the <a href=\'https://wp-staging.com/docs/wp-staging-settings-for-small-servers/\' target=\'_blank\'>WP Staging Small Server Settings</a> or submit an error report and contact us.\n\ ');
              }
            },
            429: function _() {
              if (tryCount >= retryLimit) {
                WPStagingCommon.showError('Error 429 - It looks like your server is rate limiting ajax requests. Please try to resume after a minute. If this still not works try the <a href=\'https://wp-staging.com/docs/wp-staging-settings-for-small-servers/\' target=\'_blank\'>WP Staging Small Server Settings</a> or submit an error report and contact us.\n\ ');
              }
            },
            403: function _() {
              if (tryCount >= retryLimit) {
                WPStagingCommon.showError('Refresh page or login again! The process should be finished successfully. \n\ ');
              }
            }
          }
        });
      }
    };
    return WPStagingCommon;
  })(jQuery);

  /**
   * Loader Modal
   */

  var WpstgLoader = /*#__PURE__*/function () {
    function WpstgLoader(wpstgObject) {
      if (wpstgObject === void 0) {
        wpstgObject = wpstg;
      }

      this.wpstgObject = wpstgObject;
    }
    /**
     * Show Swal alert with loader and send ajax request to fetch content of alert.
     * @return Promise
     */


    var _proto = WpstgLoader.prototype;

    _proto.showModal = function showModal() {
      var swalPromise = this.loadModal();
      return swalPromise;
    };

    _proto.loadModal = function loadModal() {
      return WPStagingCommon.getSwalModal(false, {
        container: 'wpstg-swal2-container wpstg-swal2-loading wpstg-swal2-loading-sm'
      }).fire({
        title: '',
        icon: 'warning',
        html: this.getAjaxLoader(),
        width: '100px',
        focusConfirm: false,
        showConfirmButton: false,
        showCancelButton: false
      });
    };

    _proto.getAjaxLoader = function getAjaxLoader() {
      return '<div class="wpstg-swal2-ajax-loader"><img src="' + this.wpstgObject.wpstgIcon + '" /></div>';
    };

    return WpstgLoader;
  }();

  var WpstgRemoteStorage = /*#__PURE__*/function () {
    function WpstgRemoteStorage(revokeButtonSelector, revokeProviderFormSelector, revokeAction, settingsButtonSelector, settingsFormSelector, settingsAction, authenticateButtonSelector, authenticateFormSelector, authenticateAction, testConnectionButtonSelector, testConnectionFieldsSelector, testConnectionAction, wpstgObject) {
      if (revokeButtonSelector === void 0) {
        revokeButtonSelector = '#wpstg-btn-provider-revoke';
      }

      if (revokeProviderFormSelector === void 0) {
        revokeProviderFormSelector = '#wpstg-provider-revoke-form';
      }

      if (revokeAction === void 0) {
        revokeAction = 'wpstg-provider-revoke';
      }

      if (settingsButtonSelector === void 0) {
        settingsButtonSelector = '#wpstg-btn-save-provider-settings';
      }

      if (settingsFormSelector === void 0) {
        settingsFormSelector = '#wpstg-provider-settings-form';
      }

      if (settingsAction === void 0) {
        settingsAction = 'wpstg-provider-settings';
      }

      if (authenticateButtonSelector === void 0) {
        authenticateButtonSelector = '#wpstg-btn-provider-authenticate';
      }

      if (authenticateFormSelector === void 0) {
        authenticateFormSelector = '#wpstg-provider-authenticate-form';
      }

      if (authenticateAction === void 0) {
        authenticateAction = 'wpstg-provider-authenticate';
      }

      if (testConnectionButtonSelector === void 0) {
        testConnectionButtonSelector = '#wpstg-btn-provider-test-connection';
      }

      if (testConnectionFieldsSelector === void 0) {
        testConnectionFieldsSelector = '#wpstg-provider-test-connection-fields';
      }

      if (testConnectionAction === void 0) {
        testConnectionAction = 'wpstg-provider-test-connection';
      }

      if (wpstgObject === void 0) {
        wpstgObject = wpstg;
      }

      this.revokeButtonSelector = revokeButtonSelector;
      this.revokeForm = qs(revokeProviderFormSelector);
      this.revokeAction = revokeAction;
      this.settingsButtonSelector = settingsButtonSelector;
      this.settingsForm = qs(settingsFormSelector);
      this.settingsAction = settingsAction;
      this.authenticateButtonSelector = authenticateButtonSelector;
      this.authenticateForm = qs(authenticateFormSelector);
      this.authenticateAction = authenticateAction;
      this.testConnectionButtonSelector = testConnectionButtonSelector;
      this.testConnectionFields = qs(testConnectionFieldsSelector);
      this.testConnectionAction = testConnectionAction;
      this.wpstgObject = wpstgObject;
      this.notyf = new Notyf({
        duration: 2000,
        position: {
          x: 'center',
          y: 'bottom'
        },
        dismissible: true,
        types: [{
          type: 'warning',
          background: 'orange',
          icon: false
        }]
      });
      this.init();
    }

    var _proto = WpstgRemoteStorage.prototype;

    _proto.addEvents = function addEvents() {
      var _this = this;

      addEvent(document.body, 'click', this.revokeButtonSelector, /*#__PURE__*/function () {
        var _ref = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee(target) {
          return regeneratorRuntime.wrap(function _callee$(_context) {
            while (1) {
              switch (_context.prev = _context.next) {
                case 0:
                  _context.next = 2;
                  return _this.sendRevokeRequest();

                case 2:
                case "end":
                  return _context.stop();
              }
            }
          }, _callee);
        }));

        return function (_x) {
          return _ref.apply(this, arguments);
        };
      }());
      addEvent(document.body, 'click', this.settingsButtonSelector, /*#__PURE__*/function () {
        var _ref2 = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee2(target) {
          return regeneratorRuntime.wrap(function _callee2$(_context2) {
            while (1) {
              switch (_context2.prev = _context2.next) {
                case 0:
                  _context2.next = 2;
                  return _this.sendSettingsRequest();

                case 2:
                case "end":
                  return _context2.stop();
              }
            }
          }, _callee2);
        }));

        return function (_x2) {
          return _ref2.apply(this, arguments);
        };
      }());
      addEvent(document.body, 'click', this.authenticateButtonSelector, /*#__PURE__*/function () {
        var _ref3 = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee3(target) {
          return regeneratorRuntime.wrap(function _callee3$(_context3) {
            while (1) {
              switch (_context3.prev = _context3.next) {
                case 0:
                  _context3.next = 2;
                  return _this.sendAuthenticateRequest();

                case 2:
                case "end":
                  return _context3.stop();
              }
            }
          }, _callee3);
        }));

        return function (_x3) {
          return _ref3.apply(this, arguments);
        };
      }());
      addEvent(document.body, 'click', this.testConnectionButtonSelector, /*#__PURE__*/function () {
        var _ref4 = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee4(target) {
          return regeneratorRuntime.wrap(function _callee4$(_context4) {
            while (1) {
              switch (_context4.prev = _context4.next) {
                case 0:
                  _context4.next = 2;
                  return _this.sendTestConnectionRequest();

                case 2:
                case "end":
                  return _context4.stop();
              }
            }
          }, _callee4);
        }));

        return function (_x4) {
          return _ref4.apply(this, arguments);
        };
      }());
      addEvent(document.body, 'change', '[name=\'ftp_type\']', /*#__PURE__*/function () {
        var _ref5 = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee5(target) {
          return regeneratorRuntime.wrap(function _callee5$(_context5) {
            while (1) {
              switch (_context5.prev = _context5.next) {
                case 0:
                  _context5.next = 2;
                  return _this.toggleSftpFields();

                case 2:
                case "end":
                  return _context5.stop();
              }
            }
          }, _callee5);
        }));

        return function (_x5) {
          return _ref5.apply(this, arguments);
        };
      }());
      addEvent(document.body, 'change', '[name=\'s3_provider\']', /*#__PURE__*/function () {
        var _ref6 = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee6(target) {
          return regeneratorRuntime.wrap(function _callee6$(_context6) {
            while (1) {
              switch (_context6.prev = _context6.next) {
                case 0:
                  _context6.next = 2;
                  return _this.toggleS3GenericFields();

                case 2:
                case "end":
                  return _context6.stop();
              }
            }
          }, _callee6);
        }));

        return function (_x6) {
          return _ref6.apply(this, arguments);
        };
      }());
    };

    _proto.init = function init() {
      this.addEvents();
    };

    _proto.sendRevokeRequest = /*#__PURE__*/function () {
      var _sendRevokeRequest = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee7() {
        var provider, response, data;
        return regeneratorRuntime.wrap(function _callee7$(_context7) {
          while (1) {
            switch (_context7.prev = _context7.next) {
              case 0:
                provider = this.revokeForm.querySelector('input[name="provider"]').value;
                _context7.prev = 1;
                _context7.next = 4;
                return fetch(this.wpstgObject.ajaxUrl, {
                  method: 'POST',
                  credentials: 'same-origin',
                  body: new URLSearchParams({
                    action: this.revokeAction,
                    accessToken: this.wpstgObject.accessToken,
                    nonce: this.wpstgObject.nonce,
                    provider: provider
                  }),
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                  }
                });

              case 4:
                response = _context7.sent;

                if (!response.ok) {
                  console.error("An error has occured: " + response.status);
                }

                _context7.next = 8;
                return response.json();

              case 8:
                data = _context7.sent;

                if (!data.success) {
                  _context7.next = 13;
                  break;
                }

                this.notyf.success(data.message);
                setTimeout(function () {
                  window.location.reload();
                }, 3000);
                return _context7.abrupt("return");

              case 13:
                this.notyf.error(data.message);
                _context7.next = 19;
                break;

              case 16:
                _context7.prev = 16;
                _context7.t0 = _context7["catch"](1);
                console.warn(_context7.t0);

              case 19:
              case "end":
                return _context7.stop();
            }
          }
        }, _callee7, this, [[1, 16]]);
      }));

      function sendRevokeRequest() {
        return _sendRevokeRequest.apply(this, arguments);
      }

      return sendRevokeRequest;
    }();

    _proto.sendSettingsRequest = /*#__PURE__*/function () {
      var _sendSettingsRequest = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee8() {
        var loader, swal, formData, queryString, response, data;
        return regeneratorRuntime.wrap(function _callee8$(_context8) {
          while (1) {
            switch (_context8.prev = _context8.next) {
              case 0:
                loader = new WpstgLoader();
                swal = loader.showModal();
                formData = new FormData(this.settingsForm);
                formData.append('action', this.settingsAction);
                formData.append('accessToken', this.wpstgObject.accessToken);
                formData.append('nonce', this.wpstgObject.nonce);
                queryString = new URLSearchParams(formData);
                _context8.prev = 7;
                _context8.next = 10;
                return fetch(this.wpstgObject.ajaxUrl, {
                  method: 'POST',
                  credentials: 'same-origin',
                  body: queryString,
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                  }
                });

              case 10:
                response = _context8.sent;

                if (!response.ok) {
                  console.error("An error has occured: " + response.status);
                }

                _context8.next = 14;
                return response.json();

              case 14:
                data = _context8.sent;

                if (!data.success) {
                  this.notyf.error(data.message);
                }

                if (data.warning) {
                  this.notyf.open({
                    type: 'warning',
                    message: data.message
                  });
                } else {
                  this.notyf.success(data.message);
                }

                setTimeout(function () {
                  window.location.reload();
                }, 2000);
                _context8.next = 23;
                break;

              case 20:
                _context8.prev = 20;
                _context8.t0 = _context8["catch"](7);
                console.warn(_context8.t0);

              case 23:
                _context8.prev = 23;
                swal.close();
                return _context8.finish(23);

              case 26:
              case "end":
                return _context8.stop();
            }
          }
        }, _callee8, this, [[7, 20, 23, 26]]);
      }));

      function sendSettingsRequest() {
        return _sendSettingsRequest.apply(this, arguments);
      }

      return sendSettingsRequest;
    }();

    _proto.sendAuthenticateRequest = /*#__PURE__*/function () {
      var _sendAuthenticateRequest = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee9() {
        var formData, queryString, response, data;
        return regeneratorRuntime.wrap(function _callee9$(_context9) {
          while (1) {
            switch (_context9.prev = _context9.next) {
              case 0:
                formData = new FormData(this.authenticateForm);
                formData.append('action', this.authenticateAction);
                formData.append('accessToken', this.wpstgObject.accessToken);
                formData.append('nonce', this.wpstgObject.nonce);
                queryString = new URLSearchParams(formData);
                _context9.prev = 5;
                _context9.next = 8;
                return fetch(this.wpstgObject.ajaxUrl, {
                  method: 'POST',
                  credentials: 'same-origin',
                  body: queryString,
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                  }
                });

              case 8:
                response = _context9.sent;

                if (!response.ok) {
                  console.error("An error has occured: " + response.status);
                }

                _context9.next = 12;
                return response.json();

              case 12:
                data = _context9.sent;

                if (!data.success) {
                  _context9.next = 17;
                  break;
                }

                this.notyf.success(data.message);
                setTimeout(function () {
                  window.location.reload();
                }, 2000);
                return _context9.abrupt("return");

              case 17:
                this.notyf.error(data.message);
                _context9.next = 23;
                break;

              case 20:
                _context9.prev = 20;
                _context9.t0 = _context9["catch"](5);
                console.warn(_context9.t0);

              case 23:
              case "end":
                return _context9.stop();
            }
          }
        }, _callee9, this, [[5, 20]]);
      }));

      function sendAuthenticateRequest() {
        return _sendAuthenticateRequest.apply(this, arguments);
      }

      return sendAuthenticateRequest;
    }();

    _proto.sendTestConnectionRequest = /*#__PURE__*/function () {
      var _sendTestConnectionRequest = _asyncToGenerator( /*#__PURE__*/regeneratorRuntime.mark(function _callee10() {
        var loader, swal, fields, formData, queryString, response, data;
        return regeneratorRuntime.wrap(function _callee10$(_context10) {
          while (1) {
            switch (_context10.prev = _context10.next) {
              case 0:
                loader = new WpstgLoader();
                swal = loader.showModal();
                fields = this.testConnectionFields.querySelectorAll('[name]');
                formData = new FormData();
                formData.append('action', this.testConnectionAction);
                formData.append('accessToken', this.wpstgObject.accessToken);
                formData.append('nonce', this.wpstgObject.nonce);
                fields.forEach(function (element) {
                  console.log(element);

                  if (element.type === 'check' || element.type === 'checkbox') {
                    formData.append(element.getAttribute('name'), element.checked);
                    return;
                  }

                  formData.append(element.getAttribute('name'), element.value);
                });
                queryString = new URLSearchParams(formData);
                _context10.prev = 9;
                _context10.next = 12;
                return fetch(this.wpstgObject.ajaxUrl, {
                  method: 'POST',
                  credentials: 'same-origin',
                  body: queryString,
                  headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                  }
                });

              case 12:
                response = _context10.sent;

                if (!response.ok) {
                  console.error("An error has occured: " + response.status);
                }

                _context10.next = 16;
                return response.json();

              case 16:
                data = _context10.sent;

                if (!data.success) {
                  _context10.next = 20;
                  break;
                }

                this.notyf.success(data.message);
                return _context10.abrupt("return");

              case 20:
                this.notyf.error(data.message);
                _context10.next = 26;
                break;

              case 23:
                _context10.prev = 23;
                _context10.t0 = _context10["catch"](9);
                console.warn(_context10.t0);

              case 26:
                _context10.prev = 26;
                swal.close();
                return _context10.finish(26);

              case 29:
              case "end":
                return _context10.stop();
            }
          }
        }, _callee10, this, [[9, 23, 26, 29]]);
      }));

      function sendTestConnectionRequest() {
        return _sendTestConnectionRequest.apply(this, arguments);
      }

      return sendTestConnectionRequest;
    }();

    _proto.toggleSftpFields = function toggleSftpFields() {
      var ftpType = document.querySelector('[name=\'ftp_type\']');
      var sftpElements = document.querySelectorAll('.only-sftp');
      var ftpElements = document.querySelectorAll('.only-ftp');

      if (ftpType.value === 'ftp') {
        if (sftpElements.length) {
          for (var _iterator = _createForOfIteratorHelperLoose(sftpElements), _step; !(_step = _iterator()).done;) {
            var el = _step.value;
            el.classList.add('hidden');
          }
        }

        if (ftpElements.length) {
          for (var _iterator2 = _createForOfIteratorHelperLoose(ftpElements), _step2; !(_step2 = _iterator2()).done;) {
            var _el = _step2.value;

            _el.classList.remove('hidden');
          }
        }

        return;
      }

      if (sftpElements.length) {
        for (var _iterator3 = _createForOfIteratorHelperLoose(sftpElements), _step3; !(_step3 = _iterator3()).done;) {
          var _el2 = _step3.value;

          _el2.classList.remove('hidden');
        }
      }

      if (ftpElements.length) {
        for (var _iterator4 = _createForOfIteratorHelperLoose(ftpElements), _step4; !(_step4 = _iterator4()).done;) {
          var _el3 = _step4.value;

          _el3.classList.add('hidden');
        }
      }
    };

    _proto.toggleS3GenericFields = function toggleS3GenericFields() {
      var s3Provider = document.querySelector('[name=\'s3_provider\']');
      var isCustomProvider = s3Provider.value === '';
      var customFieldsContainer = document.getElementById('wpstg-s3-custom-provider-fields');
      customFieldsContainer.style.display = isCustomProvider ? 'block' : 'none';
    };

    return WpstgRemoteStorage;
  }();

  var WPStagingPro = function ($) {
    var that = {
      isCancelled: false,
      isFinished: false,
      getLogs: false,
      tableSelector: null,
      fileSelector: null
    }; // Cache Elements

    var cache = {
      elements: []
    };
    /**
       * Get / Set Cache for Selector
       * @param {String} selector
       * @return {*}
       */

    cache.get = function (selector) {
      // It is already cached!
      if ($.inArray(selector, cache.elements) !== -1) {
        return cache.elements[selector];
      } // Create cache and return


      cache.elements[selector] = jQuery(selector);
      return cache.elements[selector];
    };
    /**
       * Refreshes given cache
       * @param {String} selector
       */


    cache.refresh = function (selector) {
      selector.elements[selector] = jQuery(selector);
    };
    /**
       * Ajax Scanning before starting push process
       */


    var startScanning = function startScanning() {
      // Scan db and file system
      var $workFlow = cache.get('#wpstg-workflow');
      $workFlow // Load scanning data
      .on('click', '.wpstg-push-changes', function (e) {
        e.preventDefault();
        var $this = $(this); // Disable button

        if ($this.attr('disabled')) {
          return false;
        } // Add loading overlay


        $workFlow.addClass('loading'); // Get clone id
        // var cloneID = $this.data("clone");
        // Get clone id

        var cloneID = $(this).data('clone'); // Prepare data

        that.data = {
          action: 'wpstg_scan',
          clone: cloneID,
          accessToken: wpstg.accessToken,
          nonce: wpstg.nonce
        }; // Send ajax request

        WPStaging.ajax(that.data, function (response) {
          if (response.length < 1) {
            showError('Something went wrong! No response.  Go to WP Staging > Settings and lower \'File Copy Limit\' and \'DB Query Limit\'. Also set \'CPU Load Priority to low \'' + 'and try again. If that does not help, ' + '<a href=\'https://wp-staging.com/support/\' target=\'_blank\'>open a support ticket</a> ');
          } // Styling of elements


          $workFlow.removeClass('loading').html(response);
          WPStaging.switchStep(2);
          cache.get('.wpstg-step3-cloning').hide();
          cache.get('.wpstg-step3-pushing').show();
          cache.get('.wpstg-loader').hide();
          that.tableSelector = new WpstgPushTableSelection();
          that.fileSelector = new WpstgPushFileSelection();
          that.tableSelector.countSelectedTables();
          that.fileSelector.countSelectedFiles(); // cache.get(".wpstg-loader").hide();
        }, 'HTML');
      }) // Previous Button
      .on('click', '.wpstg-prev-step-link', function (e) {
        e.preventDefault();
        WPStaging.loadOverview();
      }).on('click', '#wpstg-use-target-dir', function (e) {
        e.preventDefault();
        $('#wpstg_clone_dir').val(this.getAttribute('data-path'));
      }).on('click', '#wpstg-use-target-hostname', function (e) {
        e.preventDefault();
        $('#wpstg_clone_hostname').val(this.getAttribute('data-uri'));
      }).on('change', '#wpstg-delete-upload-before-pushing', function (e) {
        if (e.currentTarget.checked) {
          $('#wpstg-backup-upload-container').show();
        } else {
          $('#wpstg-backup-upload-container').hide();
          $('#wpstg-backup-upload-before-pushing').removeAttr('checked');
        }
      });
    }; // Start the whole pushing process


    var startProcess = function startProcess() {
      var $workFlow = cache.get('#wpstg-workflow'); // Click push changes button

      $workFlow.on('click', '#wpstg-push-changes', function (e) {
        e.preventDefault(); // Hide db tables and folder selection

        cache.get('.wpstg-tabs-wrapper').hide();
        cache.get('#wpstg-push-changes').hide(); // Show confirmation modal

        var cloneName = cache.get('#wpstg-push-changes').data('clone-name');
        var html = '<p class=\'wpstg-push-confirmation-message\'><b>WAIT!</b> This will overwrite the production/live site and its plugins, themes and media assets with data from the staging site: <b>"' + cloneName + '"</b>.  <br/><br/>Database data will be overwritten for each selected table. Take care if you use a shop system like WooCommerce and check out our FAQ! <br/><br/><b>IMPORTANT:</b> Before you proceed make sure that you have a full site backup. If the pushing process is not successful contact us at <a href=\'mailto:support@wp-staging.com\'>support@wp-staging.com</a> or use the <b>REPORT ISSUE</b> button.</p>';
        confirmModal('Confirm Push!', html, 'Push', 'wpstg-confirm-push').then(function (result) {
          if (result.value) {
            cache.get('#wpstg-push-changes').attr('disabled', true);
            cache.get('.wpstg-prev-step-link').attr('disabled', true);
            cache.get('#wpstg-scanning-files').hide();
            cache.get('.wpstg-progress-bar-wrapper').show();
            cache.get('#wpstg-cancel-pushing').show(); // show cancel button

            WPStaging.switchStep(3);
            window.addEventListener('beforeunload', WPStaging.warnIfClosingDuringProcess);
            processing();
            return;
          } // Show db tables and folder selection


          cache.get('.wpstg-tabs-wrapper').show();
          cache.get('#wpstg-push-changes').show();
        });
      }) // Cancel pushing process
      .on('click', '#wpstg-cancel-pushing', function () {
        if (!confirm('Are you sure you want to cancel pushing process?')) {
          return false;
        }

        cancelPushingProcess();
      });
    };

    var cancelPushingProcess = function cancelPushingProcess() {
      WPStaging.ajax({
        action: 'wpstg_cancel_push_processing',
        accessToken: wpstg.accessToken,
        nonce: wpstg.nonce
      }, function (response) {
        if (response.success) {
          that.isCancelled = true;
          cache.get('.wpstg-loader').hide();
          cache.get('#wpstg-cancel-pushing').hide();
          window.removeEventListener('beforeunload', WPStaging.warnIfClosingDuringProcess);
          WPStaging.loadOverview();
          return;
        }
      }, 'json', false);
    };
    /**
       * Start ajax processing
       * @return string
       */


    var processing = function processing() {
      // Show loader gif
      cache.get('.wpstg-loader').show(); // Show logging window

      cache.get('.wpstg-log-details').show(); // Get clone id

      var cloneID = cache.get('#wpstg-push-changes').data('clone');
      var deleteUploadsBeforePush = cache.get('#wpstg-delete-upload-before-pushing')[0].checked;
      var backupUploadsBeforePush = false;

      if (deleteUploadsBeforePush) {
        backupUploadsBeforePush = cache.get('#wpstg-backup-upload-before-pushing')[0].checked;
      }

      var includedTables = getSelectedTablesToPush();
      var excludedTables = getExcludedTables();
      var allTablesExcluded = false;
      that.isCancelled = false;

      if (includedTables.length > excludedTables.length) {
        includedTables = '';
      } else if (excludedTables.length > includedTables.length) {
        excludedTables = '';
        allTablesExcluded = includedTables === '';
      }

      WPStaging.ajax({
        action: 'wpstg_push_processing',
        accessToken: wpstg.accessToken,
        nonce: wpstg.nonce,
        clone: cloneID,
        includedTables: includedTables,
        excludedTables: excludedTables,
        allTablesExcluded: allTablesExcluded,
        selectedTablesWithoutPrefix: getSelectedTablesWithoutPrefix(),
        includedDirectories: getIncludedDirectories(),
        excludedDirectories: getExcludedDirectories(),
        extraDirectories: getIncludedExtraDirectories(),
        createBackupBeforePushing: cache.get('#wpstg-create-backup-before-pushing')[0].checked,
        deletePluginsAndThemes: cache.get('#wpstg-remove-uninstalled-plugins-themes')[0].checked,
        deleteUploadsBeforePushing: deleteUploadsBeforePush,
        backupUploadsBeforePushing: backupUploadsBeforePush
      }, function (response) {
        if (that.isCancelled) {
          cancelPushingProcess();
          cache.get('.wpstg-loader').hide();
          cache.get('#wpstg-cancel-pushing').hide();
          window.removeEventListener('beforeunload', WPStaging.warnIfClosingDuringProcess);
          return;
        } // Undefined Error


        if (false === response) {
          showError('Something went wrong! Error: No response.  Go to WP Staging > Settings and lower \'File Copy Limit\' and \'DB Query Limit\'. Also set \'CPU Load Priority to low \'' + 'and try again. If that does not help, ' + '<a href=\'https://wp-staging.com/support/\' target=\'_blank\'>open a support ticket</a> ');
          cache.get('.wpstg-loader').hide();
          window.removeEventListener('beforeunload', WPStaging.warnIfClosingDuringProcess);
          return;
        } // Throw Error


        if ('undefined' !== typeof response.error && response.error) {
          WPStaging.showError('Something went wrong! Error: ' + response.message + '.  Go to WP Staging > Settings and lower \'File Copy Limit\' and \'DB Query Limit\'. Also set \'CPU Load Priority to low \'' + 'and try again. If that does not help, ' + '<a href=\'https://wp-staging.com/support/\' target=\'_blank\'>open a support ticket</a> ');
          window.removeEventListener('beforeunload', WPStaging.warnIfClosingDuringProcess);
          return;
        } // Add Log messages


        if ('undefined' !== typeof response.last_msg && response.last_msg) {
          WPStaging.getLogs(response.last_msg);
        } // Continue processing


        if (false === response.status) {
          progressBar(response);
          setTimeout(function () {
            cache.get('.wpstg-loader').show();
            processing();
          }, wpstg.delayReq);
        } else if (true === response.status) {
          progressBar(response);
          processing();
        } else if ('finished' === response.status || 'undefined' !== typeof response.job_done && response.job_done) {
          window.removeEventListener('beforeunload', WPStaging.warnIfClosingDuringProcess);
          isFinished(response);
        }
      }, 'json', false);
    };
    /**
       * Test database connection
       * @return object
       */


    var connectDatabase = function connectDatabase() {
      var $workFlow = cache.get('#wpstg-workflow');
      $workFlow.on('click', '#wpstg-db-connect', function (e) {
        e.preventDefault();
        cache.get('.wpstg-loader').show();
        cache.get('#wpstg-db-status').hide();
        WPStaging.ajax({
          action: 'wpstg_database_connect',
          accessToken: wpstg.accessToken,
          nonce: wpstg.nonce,
          databaseUser: cache.get('#wpstg_db_username').val(),
          databasePassword: cache.get('#wpstg_db_password').val(),
          databaseServer: cache.get('#wpstg_db_server').val(),
          databaseDatabase: cache.get('#wpstg_db_database').val(),
          databasePrefix: cache.get('#wpstg_db_prefix').val()
        }, function (response) {
          // Undefined Error
          if (false === response) {
            showError('Something went wrong! Error: No response.' + 'Please try again. If that does not help, ' + '<a href=\'https://wp-staging.com/support/\' target=\'_blank\'>open a support ticket</a> ');
            cache.get('.wpstg-loader').hide();
            cache.get('#wpstg-db-status').remove();
            cache.get('#wpstg-error-details').hide();
            cache.get('#wpstg-db-connect').after('<span id="wpstg-db-status" class="wpstg-failed"> Failed</span>');
            return;
          } // Throw Error


          if ('undefined' !== typeof response.errors && response.errors) {
            WPStaging.showError('Something went wrong! Error: ' + response.errors + ' Please try again. If that does not help, ' + '<a href=\'https://wp-staging.com/support/\' target=\'_blank\'>open a support ticket</a> ');
            cache.get('.wpstg-loader').hide();
            cache.get('#wpstg-db-status').hide();
            cache.get('#wpstg-db-error').remove();
            cache.get('#wpstg-db-connect').after('<span id="wpstg-db-status" class="wpstg-failed"> Failed</span><br/><span id="wpstg-db-error" class="wpstg--red">Error: ' + response.errors + '</span>');
            return;
          }

          if ('undefined' !== typeof response.success && response.success) {
            cache.get('.wpstg-loader').hide();
            cache.get('#wpstg-db-status').hide();
            cache.get('#wpstg-error-details').hide();
            cache.get('#wpstg-db-error').hide();
            cache.get('#wpstg-db-connect').after('<span id="wpstg-db-status" class="wpstg-success"> Success</span>');
          }
        }, 'json', false);
      }); // Make form fields editable

      $workFlow.on('click', '#wpstg-ext-db', function () {
        if (this.checked) {
          cache.get('#wpstg_db_server').removeAttr('readonly');
          cache.get('#wpstg_db_username').removeAttr('readonly');
          cache.get('#wpstg_db_password').removeAttr('readonly');
          cache.get('#wpstg_db_database').removeAttr('readonly');
          cache.get('#wpstg_db_prefix').removeAttr('readonly');
        } else {
          cache.get('#wpstg_db_server').attr('readonly', true).val('');
          cache.get('#wpstg_db_username').attr('readonly', true).val('');
          cache.get('#wpstg_db_password').attr('readonly', true).val('');
          cache.get('#wpstg_db_database').attr('readonly', true).val('');
          cache.get('#wpstg_db_prefix').attr('readonly', true).val('');
        }
      });
    };

    var editCloneData = function editCloneData() {
      // Scan db and file system
      var $workFlow = cache.get('#wpstg-workflow');
      $workFlow // Load scanning data
      .on('click', '.wpstg-edit-clone-data', function (e) {
        e.preventDefault();
        var $this = $(this); // Disable button

        if ($this.attr('disabled')) {
          return false;
        } // Get clone id


        var cloneID = $(this).data('clone'); // Prepare data

        that.data = {
          action: 'wpstg_edit_clone_data',
          clone: cloneID,
          accessToken: wpstg.accessToken,
          nonce: wpstg.nonce
        }; // Send ajax request

        WPStaging.ajax(that.data, function (response) {
          $workFlow.html(response);
        }, 'HTML');
      }).on('click', '.wpstg-prev-step-link', function (e) {
        e.preventDefault();
        WPStaging.loadOverview();
      }).on('click', '#wpstg-save-clone-data', function (e) {
        e.preventDefault();
        var idPrefix = '#wpstg-edit-clone-data-';
        var cloneID = cache.get(idPrefix + 'clone-id').val();
        var cloneName = cache.get(idPrefix + 'clone-name').val();
        var directoryName = cache.get(idPrefix + 'directory-name').val();
        var path = cache.get(idPrefix + 'path').val();
        var url = cache.get(idPrefix + 'url').val();
        var prefix = cache.get(idPrefix + 'prefix').val();
        var externalDBUser = cache.get(idPrefix + 'database-user').val();
        var externalDBPassword = cache.get(idPrefix + 'database-password').val();
        var externalDBDatabase = cache.get(idPrefix + 'database-database').val();
        var externalDBHost = cache.get(idPrefix + 'database-server').val();
        var externalDBPrefix = cache.get(idPrefix + 'database-prefix').val(); // Prepare data

        that.data = {
          action: 'wpstg_save_clone_data',
          clone: cloneID,
          cloneName: cloneName,
          directoryName: directoryName,
          path: path,
          url: url,
          prefix: prefix,
          externalDBUser: externalDBUser,
          externalDBPassword: externalDBPassword,
          externalDBDatabase: externalDBDatabase,
          externalDBHost: externalDBHost,
          externalDBPrefix: externalDBPrefix,
          accessToken: wpstg.accessToken,
          nonce: wpstg.nonce
        };
        WPStaging.ajax(that.data, function (response) {
          if (response === 'Success') {
            WPStaging.loadOverview();
          } else {
            alert(response);
          }
        }, 'HTML');
      });
    };
    /**
       * All jobs are finished
       * @param {object} response
       * @return object
       */


    var isFinished = function isFinished(response) {
      progressBar(response);
      cache.get('.wpstg-loader').text('Finished');
      cache.get('.wpstg-loader').addClass('wpstg-finished');
      cache.get('.wpstg-prev-step-link').attr('disabled', false);
      cache.get('#wpstg-cancel-pushing').hide();
      WPStagingCommon.getSwalModal(true, {
        confirmButton: 'wpstg--btn--confirm wpstg-green-button wpstg-button wpstg-link-btn wpstg-100-width',
        popup: 'wpstg-swal-popup wpstg-push-finished centered-modal'
      }).fire({
        title: 'Push Successful!',
        icon: 'success',
        html: 'Delete site cache if required!',
        width: '500px',
        focusConfirm: true
      });
    };
    /**
       * Get Included (Selected) Prefixed Database Tables
       * @return {Array}
       */


    var getSelectedTablesToPush = function getSelectedTablesToPush() {
      if (that.tableSelector === null) {
        return '';
      }

      return that.tableSelector.getIncludedTables();
    };
    /**
       * Get Excluded (Unchecked) Prefixed Database Tables
       * @return {Array}
       */


    var getExcludedTables = function getExcludedTables() {
      if (that.tableSelector === null) {
        return '';
      }

      return that.tableSelector.getExcludedTables();
    };
    /**
       * Get Non prefixed selected Database Tables
       * @return {Array}
       */


    var getSelectedTablesWithoutPrefix = function getSelectedTablesWithoutPrefix() {
      if (that.tableSelector === null) {
        return '';
      }

      return that.tableSelector.getSelectedTablesWithoutPrefix();
    };
    /**
       * A confirmation modal
       *
       * @param html
       * @param confirmText
       * @param confirmButtonClass
       * @return Promise
       */


    var confirmModal = function confirmModal(title, html, confirmText, confirmButtonClass) {
      return WPStagingCommon.getSwalModal(false, {
        container: 'wpstg-swal-push-container',
        confirmButton: confirmButtonClass + ' wpstg--btn--confirm wpstg-blue-primary wpstg-button wpstg-link-btn'
      }).fire({
        title: title,
        icon: 'warning',
        html: html,
        width: '750px',
        focusConfirm: false,
        confirmButtonText: confirmText,
        showCancelButton: true
      });
    };
    /**
       * Get Included Directories
       * @return {Array}
       */


    var getIncludedDirectories = function getIncludedDirectories() {
      var includedDirectories = [];
      $('.wpstg-dir input:checked').each(function () {
        var $this = $(this);

        if (!$this.parent('.wpstg-dir').parents('.wpstg-dir').children('.wpstg-expand-dirs').hasClass('disabled')) {
          includedDirectories.push($this.val());
        }
      });
      return includedDirectories;
    };
    /**
       * Get Excluded Directories
       * @return {Array}
       */


    var getExcludedDirectories = function getExcludedDirectories() {
      var excludedDirectories = [];
      $('.wpstg-dir input:not(:checked)').each(function () {
        var $this = $(this);
        excludedDirectories.push($this.val());
      });
      return excludedDirectories;
    };
    /**
       * Get Included Extra Directories
       * @return {Array}
       */


    var getIncludedExtraDirectories = function getIncludedExtraDirectories() {
      var extraDirectories = [];

      if (!$('#wpstg_extraDirectories').val()) {
        return extraDirectories;
      }

      var extraDirectories = $('#wpstg_extraDirectories').val().split(/\r?\n/);
      return extraDirectories;
    };

    var progressBar = function progressBar(response, restart) {
      if ('undefined' === typeof response.percentage) {
        return false;
      }

      if (response.job === 'JobCreateBackup') {
        cache.get('#wpstg-progress-backup').width(response.percentage * 0.15 + '%').html(response.percentage + '%');
        cache.get('#wpstg-processing-status').html(response.percentage.toFixed(0) + '%' + ' - Step 1 of 4 Creating backup...');
      }

      if (response.job === 'jobFileScanning' || response.job === 'jobCopy') {
        cache.get('#wpstg-progress-backup').css('background-color', '#3bc36b');
        cache.get('#wpstg-progress-backup').html('1. Backup'); // Assumption: All previous steps are done.
        // This avoids bugs where some steps are skipped and the progress bar is incomplete as a result

        cache.get('#wpstg-progress-backup').width('15%');
        var percentage;

        if (response.job === 'jobFileScanning') {
          percentage = response.percentage / 2;
        } else {
          percentage = 50 + response.percentage / 2;
        }

        cache.get('#wpstg-progress-files').width(percentage * 0.3 + '%').html(percentage.toFixed(0) + '%');
        cache.get('#wpstg-processing-status').html(percentage.toFixed(0) + '%' + ' - Step 2 of 4 Copying files...');
      }

      if (response.job === 'jobCopyDatabaseTmp' || response.job === 'jobSearchReplace' || response.job === 'jobData') {
        cache.get('#wpstg-progress-files').css('background-color', '#3bc36b');
        cache.get('#wpstg-progress-files').html('2. Files');
        cache.get('#wpstg-progress-files').width('30%');
        var _percentage = 0;

        if (response.job === 'jobCopyDatabaseTmp') {
          _percentage = response.percentage / 3;
        } else if (response.job === 'jobSearchReplace') {
          _percentage = 100 / 3 + response.percentage / 3;
        } else {
          _percentage = 200 / 3 + response.percentage / 3;
        }

        cache.get('#wpstg-progress-data').width(_percentage * 0.4 + '%').html(_percentage.toFixed(0) + '%');
        cache.get('#wpstg-processing-status').html(_percentage.toFixed(0) + '%' + ' - Step 3 of 4 Copying data...');
      }

      if (response.job === 'jobDatabaseRename') {
        cache.get('#wpstg-progress-data').css('background-color', '#3bc36b');
        cache.get('#wpstg-progress-data').html('3. Data');
        cache.get('#wpstg-progress-data').width('40%');
        cache.get('#wpstg-progress-finishing').width(response.percentage * 0.15 + '%').html(response.percentage + '%');
        cache.get('#wpstg-processing-status').html(response.percentage.toFixed(0) + '%' + ' - Step 4 of 4 Finishing migration...');
      }

      if (response.status === 'finished') {
        cache.get('#wpstg-progress-finishing').css('background-color', '#3bc36b');
        cache.get('#wpstg-progress-finishing').html('4. Finishing migration');
        cache.get('#wpstg-progress-finishing').width('15%');
        cache.get('#wpstg-processing-status').html(response.percentage.toFixed(0) + '%' + ' - Pushing Process Finished');
      }
    };

    that.init = function () {
      startProcess();
      startScanning();
      connectDatabase();
      editCloneData();
      new WpstgCloneEdit();
      new WpstgRemoteStorage();
    };

    return that;
  }(jQuery);

  jQuery(document).ready(function ($) {
    WPStagingPro.init();
    jQuery(document).on('click', '#wpstg-update-mail-settings', function (e) {
      e.preventDefault();
      $('#wpstg-update-mail-settings').attr('disabled', 'disabled');
      var data = {
        action: 'wpstg_update_staging_mail_settings',
        emailsAllowed: $('#wpstg_allow_emails').is(':checked'),
        accessToken: wpstg.accessToken,
        nonce: wpstg.nonce
      };
      jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: data,
        error: function error(xhr, textStatus, errorThrown) {
          WPStagingCommon.getSwalModal().fire('Unknown error', 'Please get in contact with us to solve it support@wp-staging.com', 'error');
        },
        success: function success(response) {
          var alertType = 'error';

          if (response.success) {
            alertType = 'success';
          }

          WPStagingCommon.getSwalModal().fire('', response.message, alertType).then(function () {
            jQuery('.wpstg-mails-notice').slideUp('fast');
          });
          $('#wpstg-update-mail-settings').removeAttr('disabled');
          return true;
        },
        statusCode: {
          404: function _() {
            WPStagingCommon.getSwalModal().fire('404', 'Something went wrong; can\'t find ajax request URL! Please get in contact with us to solve it support@wp-staging.com', 'error');
          },
          500: function _() {
            WPStagingCommon.getSwalModal().fire('500', 'Something went wrong; internal server error while processing the request! Please get in contact with us to solve it support@wp-staging.com', 'error');
          }
        }
      });
    });
  }); // export default {}

}());
//# sourceMappingURL=wpstg-admin-pro.js.map
