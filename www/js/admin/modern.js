$(document).ready(function () {

    // Toggle Search
    $('.show-search').click(function () {
        $('.search-form').css('margin-top', '0');
    });

    $('.close-search').click(function () {
        $('.search-form').css('margin-top', '-60px');
    });

    // Panel Control
    $('.panel-collapse').click(function () {
        $(this).closest(".panel").children('.panel-body').slideToggle('fast');
    });

    // Must equal the CSS breakpoint that moves the sidebar off-canvas
    // (custom.css "@media (max-width: 991px)"); a different value leaves a
    // width range where the toggle does nothing.
    var MOBILE_BREAKPOINT = 991;

    // Push Menu (mobile off-canvas slide-in only — see breakpoint note above).
    // Does NOT add/remove .page-inner's "sidebar-visible" class: that class
    // is the base theme's own legacy off-canvas mechanism, which PUSHES the
    // content area sideways via "transform: translate3d(160px, 0, 0)"
    // (modern.css, @media max-width: 767px). Our Mosaic sidebar is an
    // overlay (it sits on top of the content via z-index, the content never
    // moves), so that push transform was pure extra width with nothing to
    // show for it — confirmed via Playwright that opening the sidebar added
    // exactly 160px of horizontal scroll (document scrollWidth 438 -> 598 at
    // a 438px viewport) purely from this leftover class, even after the
    // page itself had zero overflow with the sidebar closed.
    $('.push-sidebar').click(function () {
        if (window.innerWidth > MOBILE_BREAKPOINT) {
            return;
        }
        var hidden = $('.sidebar');

        if (hidden.hasClass('visible')) {
            hidden.removeClass('visible');
        }
        else {
            hidden.addClass('visible');
        }
    });
    // a tap outside the open off-canvas sidebar (its dimmed backdrop) closes it
    $(document).on('click', function (e) {
        if (window.innerWidth <= MOBILE_BREAKPOINT && $('.sidebar').hasClass('visible')
            && !$(e.target).closest('.sidebar, .push-sidebar').length) {
            $('.sidebar').removeClass('visible');
            e.preventDefault();
        }
    });

    // Desktop collapse/expand of the sidebar, remembered across reloads. The
    // toggle button has both "push-sidebar" and "mosaic-sidebar-toggle", so
    // each handler only acts on its own side of the breakpoint.
    if (window.innerWidth > MOBILE_BREAKPOINT && localStorage.getItem('mosaicSidebarCollapsed') === '1') {
        $('body').addClass('mosaic-sidebar-collapsed');
    }
    $('.mosaic-sidebar-toggle').click(function () {
        if (window.innerWidth <= MOBILE_BREAKPOINT) {
            return;
        }
        var collapsed = $('body').toggleClass('mosaic-sidebar-collapsed').hasClass('mosaic-sidebar-collapsed');
        localStorage.setItem('mosaicSidebarCollapsed', collapsed ? '1' : '0');
    });

    // Sidebar Menu
    var parent, ink, d, x, y;

    var parts = location.pathname.split('/');
    var moduleUrl = parts.length >= 4 ? '/' + parts[1] + '/' + parts[2] + '/' : '/admin/';
    var menuItem = $(".menu a[href='" + moduleUrl + "']");
    if (menuItem.length) {
        menuItem.parents("li").addClass('active');
        menuItem.parents("li.droplink:not(.top-level)").addClass('open');
    }

    $('.sidebar .accordion-menu li .sub-menu').slideUp(0);
    $('.sidebar .accordion-menu li.open .sub-menu').slideDown(0);
    $('.small-sidebar .sidebar .accordion-menu li.open .sub-menu').hide(0);
    $('.sidebar .accordion-menu > li.droplink > a').click(function () {

        if ($('body').hasClass('.small-sidebar')) {
            return;
        }
        ;

        if ($('body').hasClass('.page-horizontal-bar')) {
            return;
        }
        ;

        if ($('body').hasClass('.hover-menu')) {
            return;
        }
        ;

        var menu = $('.sidebar .menu'), sidebar = $('.page-sidebar-inner'), page = $('.page-content'), sub = $(this).next(), el = $(this);

        menu.find('li').removeClass('open');
        $('.sub-menu').slideUp(200, function () {
            sidebarAndContentHeight();
        });
        sidebarAndContentHeight();

        if (!sub.is(':visible')) {
            $(this).parent('li').addClass('open');
            $(this).next('.sub-menu').slideDown(200, function () {
                sidebarAndContentHeight();
            });
        }
        else {
            sub.slideUp(200, function () {
                sidebarAndContentHeight();
            });
        }
        return false;
    });

    $('.sidebar .accordion-menu .sub-menu li.droplink > a').click(function () {

        var menu = $(this).parent().parent(), sidebar = $('.page-sidebar-inner'), page = $('.page-content'), sub = $(this).next(), el = $(this);

        menu.find('li').removeClass('open');
        sidebarAndContentHeight();

        if (!sub.is(':visible')) {
            $(this).parent('li').addClass('open');
            $(this).next('.sub-menu').slideDown(200, function () {
                sidebarAndContentHeight();
            });
        }
        else {
            sub.slideUp(200, function () {
                sidebarAndContentHeight();
            });
        }
        return false;
    });

    // No-op: the sidebar/content layout is now CSS `position: fixed` (see
    // www/css/admin/custom.css "FINAL LAYOUT FIX OVERRIDES"), which sizes
    // itself natively and needs no JS height sync. Kept as a no-op instead
    // of removing every call site — this used to overwrite .page-inner's
    // entire `style` attribute (`content.attr('style', ...)`) on every
    // menu click, wiping out whatever the fixed-layout CSS had just applied
    // mid-animation and blocking the submenu from actually appearing.
    var sidebarAndContentHeight = function () {};

    sidebarAndContentHeight();

    window.onresize = sidebarAndContentHeight;


    // Logo text on Collapsed Sidebar
    var str = $('.navbar .logo-box a span').text(),
        smTxt = (str.slice(0,1));

    $('.small-sidebar .navbar .logo-box a span').html($('.navbar .logo-box a span').text() == smTxt ? str : smTxt);


    // Sidebar Toggle
    $('.sidebar-toggle').click(function () {
        toggleSidebarCheck.click();
    });


    // Hover-menu flyouts: the scrolling sidebar (overflow-y: auto) forces
    // overflow-x to auto too and clips absolutely positioned flyouts, so each
    // flyout is placed with position: fixed from the hovered <li>'s rect
    // ('important' because the CSS positions it with !important).
    if ($('body').hasClass('hover-menu')) {
        $(document).on('mouseenter', '.sidebar .accordion-menu li.droplink', function () {
            var flyout = this.querySelector(':scope > ul.sub-menu');
            // off-canvas sidebar (phones, tablets): sub-menus open in place (custom.css)
            if (!flyout || window.innerWidth <= MOBILE_BREAKPOINT) {
                return;
            }
            var rect = this.getBoundingClientRect();
            flyout.style.setProperty('position', 'fixed', 'important');
            flyout.style.setProperty('top', rect.top + 'px', 'important');
            flyout.style.setProperty('left', rect.right + 'px', 'important');
            flyout.style.setProperty('margin-left', '0px', 'important');

            // Only cap the height (and allow scrolling) when the flyout's
            // own content is actually taller than the space available below
            // it in the viewport — otherwise it should size to its content,
            // not show a scrollbar it doesn't need.
            var availableHeight = window.innerHeight - rect.top - 8;
            if (flyout.scrollHeight > availableHeight) {
                flyout.style.setProperty('max-height', availableHeight + 'px', 'important');
                flyout.style.setProperty('overflow-y', 'auto', 'important');
            }
            else {
                flyout.style.removeProperty('max-height');
                flyout.style.setProperty('overflow-y', 'visible', 'important');
            }
        });
    }

});
