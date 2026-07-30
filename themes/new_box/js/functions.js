/*
 * Detect Mobile Browser
 */
if( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ) {
   jQuery('html').addClass('ismobile');
}

/* --------------------------------------------------------
 Page Loader — скрывать надёжно (не ждать вечный window.load)
 -----------------------------------------------------------*/
(function () {
    var $loader = jQuery('.page-loader');
    if (!$loader.length) {
        return;
    }
    var hidden = false;
    function hidePageLoader() {
        if (hidden) {
            return;
        }
        hidden = true;
        $loader.stop(true, true).fadeOut(300, function () {
            jQuery(this).remove();
        });
    }
    // Скрываем только когда реально загрузились ВСЕ ресурсы (window.load),
    // + небольшая задержка. Раньше гасили на DOM-ready — из-за этого спиннер
    // исчезал, пока страница ещё догружалась.
    if (document.readyState === 'complete') {
        setTimeout(hidePageLoader, 500);
    } else {
        jQuery(window).on('load', function () {
            setTimeout(hidePageLoader, 500);
        });
    }
    // Аварийный предохранитель: если window.load завис (битый ресурс) — снять через 12с.
    setTimeout(hidePageLoader, 12000);
})();

jQuery(document).ready(function(){
	/* --------------------------------------------------------
        Layout
    -----------------------------------------------------------*/
    (function () {

        //Get saved layout type from LocalStorage
        var layoutStatus = localStorage.getItem('ma-layout-status');

        if(!jQuery('#header-2')[0]) {  //Make it work only on normal headers
            if (layoutStatus == 1) {
                jQuery('body').addClass('sw-toggled');
                jQuery('#tw-switch').prop('checked', true);
            }
        }

        jQuery('body').on('change', '#toggle-width input:checkbox', function () {
            if (jQuery(this).is(':checked')) {
                setTimeout(function () {
                    jQuery('body').addClass('toggled sw-toggled');
                    localStorage.setItem('ma-layout-status', 1);
                }, 250);
            }
            else {
                setTimeout(function () {
                    jQuery('body').removeClass('toggled sw-toggled');
                    localStorage.setItem('ma-layout-status', 0);
                }, 250);
            }
        });
    })();

    /* --------------------------------------------------------
        Scrollbar
    -----------------------------------------------------------*/
    function scrollBar(selector, theme, mousewheelaxis) {
        jQuery(selector).mCustomScrollbar({
            theme: theme,
            scrollInertia: 100,
            axis:'yx',
            mouseWheel: {
                enable: true,
                axis: mousewheelaxis,
                preventDefault: true
            }
        });
    }

    if (!jQuery('html').hasClass('ismobile')) {
        //On Custom Class
        if (jQuery('.c-overflow')[0]) {
            scrollBar('.c-overflow', 'minimal-dark', 'y');
        }
    }

    /*
     * Top Search
     */
    (function(){
        jQuery('body').on('click', '#top-search > a', function(e){
            e.preventDefault();

            jQuery('#header').addClass('search-toggled');
            //jQuery('#top-search-wrap input').focus();
        });

        jQuery('body').on('click', '#top-search-close', function(e){
            e.preventDefault();

            jQuery('#header').removeClass('search-toggled');
        });
    })();

    /*
     * Sidebar
     */
    (function(){
        //Toggle
        jQuery('body').on('click', '#menu-trigger, #chat-trigger', function(e){
            e.preventDefault();
            var x = jQuery(this).data('trigger');

            jQuery(x).toggleClass('toggled');
            jQuery(this).toggleClass('open');

    	    //Close opened sub-menus
    	    jQuery('.sub-menu.toggled').not('.active').each(function(){
        		jQuery(this).removeClass('toggled');
        		jQuery(this).find('ul').hide();
    	    });



	    jQuery('.profile-menu .main-menu').hide();

            if (x == '#sidebar') {

                jQueryelem = '#sidebar';
                jQueryelem2 = '#menu-trigger';

                jQuery('#chat-trigger').removeClass('open');

                if (!jQuery('#chat').hasClass('toggled')) {
                    jQuery('#header').toggleClass('sidebar-toggled');
                }
                else {
                    jQuery('#chat').removeClass('toggled');
                }
            }

            if (x == '#chat') {
                jQueryelem = '#chat';
                jQueryelem2 = '#chat-trigger';

                jQuery('#menu-trigger').removeClass('open');

                if (!jQuery('#sidebar').hasClass('toggled')) {
                    jQuery('#header').toggleClass('sidebar-toggled');
                }
                else {
                    jQuery('#sidebar').removeClass('toggled');
                }
            }

            //When clicking outside
            if (jQuery('#header').hasClass('sidebar-toggled')) {
                jQuery(document).on('click', function (e) {
                    if ((jQuery(e.target).closest(jQueryelem).length === 0) && (jQuery(e.target).closest(jQueryelem2).length === 0)) {
                        setTimeout(function(){
                            jQuery(jQueryelem).removeClass('toggled');
                            jQuery('#header').removeClass('sidebar-toggled');
                            jQuery(jQueryelem2).removeClass('open');
                        });
                    }
                });
            }
        })

        //Submenu
        jQuery('body').on('click', '.sub-menu > a', function(e){
            e.preventDefault();
            jQuery(this).next().slideToggle(200);
            jQuery(this).parent().toggleClass('toggled');
        });
    })();

    /*
     * Clear Notification
     */
    jQuery('body').on('click', '[data-clear="notification"]', function(e){
      e.preventDefault();

      var x = jQuery(this).closest('.listview');
      var y = x.find('.lv-item');
      var z = y.size();

      jQuery(this).parent().fadeOut();

      x.find('.list-group').prepend('<i class="grid-loading hide-it"></i>');
      x.find('.grid-loading').fadeIn(1500);


      var w = 0;
      y.each(function(){
          var z = jQuery(this);
          setTimeout(function(){
          z.addClass('animated fadeOutRightBig').delay(1000).queue(function(){
              z.remove();
          });
          }, w+=150);
      })

	//Popup empty message
	setTimeout(function(){
	    jQuery('#notifications').addClass('empty');
	}, (z*150)+200);
    });

    /*
     * Dropdown Menu
     */
    if(jQuery('.dropdown')[0]) {
	//Propagate
	jQuery('body').on('click', '.dropdown.open .dropdown-menu', function(e){
	    e.stopPropagation();
	});

	jQuery('.dropdown').on('shown.bs.dropdown', function (e) {
	    if(jQuery(this).attr('data-animation')) {
		jQueryanimArray = [];
		jQueryanimation = jQuery(this).data('animation');
		jQueryanimArray = jQueryanimation.split(',');
		jQueryanimationIn = 'animated '+jQueryanimArray[0];
		jQueryanimationOut = 'animated '+ jQueryanimArray[1];
		jQueryanimationDuration = ''
		if(!jQueryanimArray[2]) {
		    jQueryanimationDuration = 500; //if duration is not defined, default is set to 500ms
		}
		else {
		    jQueryanimationDuration = jQueryanimArray[2];
		}

		jQuery(this).find('.dropdown-menu').removeClass(jQueryanimationOut)
		jQuery(this).find('.dropdown-menu').addClass(jQueryanimationIn);
	    }
	});

	jQuery('.dropdown').on('hide.bs.dropdown', function (e) {
	    if(jQuery(this).attr('data-animation')) {
    		e.preventDefault();
    		jQuerythis = jQuery(this);
    		jQuerydropdownMenu = jQuerythis.find('.dropdown-menu');

    		jQuerydropdownMenu.addClass(jQueryanimationOut);
    		setTimeout(function(){
    		    jQuerythis.removeClass('open')

    		}, jQueryanimationDuration);
    	    }
    	});
    }

    /*
     * Todo Add new item
     */
    if (jQuery('#todo-lists')[0]) {
    	//Add Todo Item
    	jQuery('body').on('click', '#add-tl-item .add-new-item', function(){
    	    jQuery(this).parent().addClass('toggled');
    	});

            //Dismiss
            jQuery('body').on('click', '.add-tl-actions > a', function(e){
                e.preventDefault();
                var x = jQuery(this).closest('#add-tl-item');
                var y = jQuery(this).data('tl-action');

                if (y == "dismiss") {
                    x.find('textarea').val('');
                    x.removeClass('toggled');
                }

                if (y == "save") {
                    x.find('textarea').val('');
                    x.removeClass('toggled');
                }
    	});
    }

    /*
     * Auto Hight Textarea
     */
    if (jQuery('.auto-size')[0]) {
	   autosize(jQuery('.auto-size'));
    }

    /*
    * Profile Menu
    */
    jQuery('body').on('click', '.profile-menu > a', function(e){
        e.preventDefault();
        jQuery(this).parent().toggleClass('toggled');
	    jQuery(this).next().slideToggle(200);
    });

    /*
     * Text Feild
     */

    //Add blue animated border and remove with condition when focus and blur
    if(jQuery('.fg-line')[0]) {
        jQuery('body').on('focus', '.fg-line .form-control', function(){
            jQuery(this).closest('.fg-line').addClass('fg-toggled');
        })

        jQuery('body').on('blur', '.form-control', function(){
            var p = jQuery(this).closest('.form-group, .input-group');
            var i = p.find('.form-control').val();

            if (p.hasClass('fg-float')) {
                if (i.length == 0) {
                    jQuery(this).closest('.fg-line').removeClass('fg-toggled');
                }
            }
            else {
                jQuery(this).closest('.fg-line').removeClass('fg-toggled');
            }
        });
    }

    //Add blue border for pre-valued fg-flot text feilds
    if(jQuery('.fg-float')[0]) {
        jQuery('.fg-float .form-control').each(function(){
            var i = jQuery(this).val();

            if (!i.length == 0) {
                jQuery(this).closest('.fg-line').addClass('fg-toggled');
            }

        });
    }

    /*
     * Input Mask
     */
    if (jQuery('.input-mask')[0]) {
        jQuery('.input-mask').mask();
    }

    /*
     * HTML Editor (Summernote)
     */
    if (jQuery('.html-editor')[0]) {
	   jQuery('.html-editor').each(function () {
            var $el = jQuery(this);
            var h = ($el.attr('id') === 'dash_intro_text') ? 480 : 150;
            $el.summernote({
                height: h
            });
        });
    }

    if(jQuery('.html-editor-click')[0]) {
        //Edit
        jQuery('body').on('click', '.hec-button', function(){
            jQuery('.html-editor-click').summernote({
                focus: true
            });
            jQuery('.hec-save').show();
        })

        //Save
        jQuery('body').on('click', '.hec-save', function(){
            jQuery('.html-editor-click').code();
            jQuery('.html-editor-click').destroy();
            jQuery('.hec-save').hide();
            notify('Content Saved Successfully!', 'success');
        });
    }

    //Air Mode
    if(jQuery('.html-editor-airmod')[0]) {
        jQuery('.html-editor-airmod').summernote({
            airMode: true
        });
    }

    /*
     * Bootstrap Growl - Notifications popups
     */
    function notify(message, type){
        jQuery.growl({
            message: message
        },{
            type: type,
            allow_dismiss: false,
            label: 'Cancel',
            className: 'btn-xs btn-inverse',
            placement: {
                from: 'top',
                align: 'right'
            },
            delay: 2500,
            animate: {
                    enter: 'animated bounceIn',
                    exit: 'animated bounceOut'
            },
            offset: {
                x: 20,
                y: 85
            }
        });
    };

    /*
     * Waves Animation
     */
    (function(){
         Waves.attach('.btn:not(.btn-icon):not(.btn-float)');
         Waves.attach('.btn-icon, .btn-float', ['waves-circle', 'waves-float']);
        Waves.init();
    })();

    /*
     * Lightbox
     */
    if (jQuery('.lightbox')[0]) {
        jQuery('.lightbox').lightGallery({
            enableTouch: true
        });
    }

    /*
     * Link prevent
     */
    jQuery('body').on('click', '.a-prevent', function(e){
        e.preventDefault();
    });

    /*
     * Collaspe Fix
     */
    if (jQuery('.collapse')[0]) {

        //Add active class for opened items
        jQuery('.collapse').on('show.bs.collapse', function (e) {
            jQuery(this).closest('.panel').find('.panel-heading').addClass('active');
        });

        jQuery('.collapse').on('hide.bs.collapse', function (e) {
            jQuery(this).closest('.panel').find('.panel-heading').removeClass('active');
        });

        //Add active class for pre opened items
        jQuery('.collapse.in').each(function(){
            jQuery(this).closest('.panel').find('.panel-heading').addClass('active');
        });
    }

    /*
     * Tooltips
     */
    if (jQuery('[data-toggle="tooltip"]')[0]) {
        jQuery('[data-toggle="tooltip"]').tooltip();
    }

    /*
     * Popover (подсказки «?» — container body, иначе клипаются overflow родителей)
     */
    if (jQuery('[data-toggle="popover"]')[0]) {
        jQuery('[data-toggle="popover"]').popover({
            container: 'body',
            trigger: 'hover focus',
            html: false,
            placement: 'auto top'
        });
    }

    /*
     * Message
     */

    //Actions
    if (jQuery('.on-select')[0]) {
        var checkboxes = '.lv-avatar-content input:checkbox';
        var actions = jQuery('.on-select').closest('.lv-actions');

        jQuery('body').on('click', checkboxes, function() {
            if (jQuery(checkboxes+':checked')[0]) {
                actions.addClass('toggled');
            }
            else {
                actions.removeClass('toggled');
            }
        });
    }

    if(jQuery('#ms-menu-trigger')[0]) {
        jQuery('body').on('click', '#ms-menu-trigger', function(e){
            e.preventDefault();
            jQuery(this).toggleClass('open');
            jQuery('.ms-menu').toggleClass('toggled');
        });
    }

    /*
     * Login
     */
    if (jQuery('.login-content')[0]) {
        //Add class to HTML. This is used to center align the logn box
        //jQuery('html').addClass('login-content');

        jQuery('body').on('click', '.login-navigation > li', function(){
            var z = jQuery(this).data('block');
            var t = jQuery(this).closest('.lc-block');

            t.removeClass('toggled');

            setTimeout(function(){
                jQuery(z).addClass('toggled');
            });

        })
    }

    /*
     * Fullscreen Browsing
     */
    if (jQuery('[data-action="fullscreen"]')[0]) {
	var fs = jQuery("[data-action='fullscreen']");
	fs.on('click', function(e) {
	    e.preventDefault();

	    //Launch
	    function launchIntoFullscreen(element) {

		if(element.requestFullscreen) {
		    element.requestFullscreen();
		} else if(element.mozRequestFullScreen) {
		    element.mozRequestFullScreen();
		} else if(element.webkitRequestFullscreen) {
		    element.webkitRequestFullscreen();
		} else if(element.msRequestFullscreen) {
		    element.msRequestFullscreen();
		}
	    }

	    //Exit
	    function exitFullscreen() {

		if(document.exitFullscreen) {
		    document.exitFullscreen();
		} else if(document.mozCancelFullScreen) {
		    document.mozCancelFullScreen();
		} else if(document.webkitExitFullscreen) {
		    document.webkitExitFullscreen();
		}
	    }

	    launchIntoFullscreen(document.documentElement);
	    fs.closest('.dropdown').removeClass('open');
	});
    }

    /*
     * Clear Local Storage
     */
    if (jQuery('[data-action="clear-localstorage"]')[0]) {
        var cls = jQuery('[data-action="clear-localstorage"]');

        cls.on('click', function(e) {
            e.preventDefault();

            swal({
                title: "Are you sure?",
                text: "All your saved localStorage values will be removed",
                type: "warning",
                showCancelButton: true,
                confirmButtonColor: "#DD6B55",
                confirmButtonText: "Yes, delete it!",
                closeOnConfirm: false
            }, function(){
                localStorage.clear();
                swal("Done!", "localStorage is cleared", "success");
            });
        });
    }

    /*
     * Profile Edit Toggle
     */
    if (jQuery('[data-pmb-action]')[0]) {
        jQuery('body').on('click', '[data-pmb-action]', function(e){
            e.preventDefault();
            var d = jQuery(this).data('pmb-action');

            if (d === "edit") {
                jQuery(this).closest('.pmb-block').toggleClass('toggled');
            }

            if (d === "reset") {
                jQuery(this).closest('.pmb-block').removeClass('toggled');
            }


        });
    }

    /*
     * IE 9 Placeholder
     */
    if(jQuery('html').hasClass('ie9')) {
        jQuery('input, textarea').placeholder({
            customClass: 'ie9-placeholder'
        });
    }


    /*
     * Listview Search
     */
    if (jQuery('.lvh-search-trigger')[0]) {


        jQuery('body').on('click', '.lvh-search-trigger', function(e){
            e.preventDefault();
            x = jQuery(this).closest('.lv-header-alt').find('.lvh-search');

            x.fadeIn(300);
            x.find('.lvhs-input').focus();
        });

        //Close Search
        jQuery('body').on('click', '.lvh-search-close', function(){
            x.fadeOut(300);
            setTimeout(function(){
                x.find('.lvhs-input').val('');
            }, 350);
        })
    }


    /*
     * Print
     */
    if (jQuery('[data-action="print"]')[0]) {
        jQuery('body').on('click', '[data-action="print"]', function(e){
            e.preventDefault();

            window.print();
        })
    }

});