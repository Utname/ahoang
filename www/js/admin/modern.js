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

    // Push Menu
    $('.push-sidebar').click(function () {
        var hidden = $('.sidebar');

        if (hidden.hasClass('visible')) {
            hidden.removeClass('visible');
            $('.page-inner').removeClass('sidebar-visible');
        }
        else {
            hidden.addClass('visible');
            $('.page-inner').addClass('sidebar-visible');
        }
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


    // Hover-menu flyout positioning (JS, not pure CSS)
    //
    // The flyout sub-menus rely on `position: absolute; left: 100%` to
    // escape the sidebar to the right. The sidebar also needs
    // `overflow-y: auto` so a long menu list can scroll. Browsers force
    // overflow-x to behave like overflow-y whenever only one of the two is
    // `visible` (https://www.w3.org/TR/css-overflow-3/#overflow-control) —
    // so as long as the sidebar scrolls vertically, ANY `overflow-x:
    // visible` written in CSS is silently downgraded to `auto`, which clips
    // every flyout no matter how the CSS is written. Confirmed directly via
    // Chrome DevTools Protocol (getComputedStyle().overflowX stayed "auto"
    // even though every matched CSS rule said "visible !important").
    //
    // `position: fixed` isn't subject to an ancestor's `overflow` at all
    // (it escapes the containing block chain to position against the
    // viewport), so this repositions each flyout to `fixed` with its
    // top/left computed from the hovered <li>'s actual screen position on
    // every hover — working at any nesting depth, since
    // getBoundingClientRect() always returns viewport-relative coordinates
    // regardless of how deeply nested the <li> is. CSS already shows/hides
    // the flyout via :hover (`.hover-menu ... > li:hover > ul.sub-menu`) —
    // this only fixes *where* it renders, using setProperty(...,
    // 'important') since the existing CSS positions it with `!important`
    // too and a plain inline style wouldn't be able to override that.
    if ($('body').hasClass('hover-menu')) {
        $(document).on('mouseenter', '.sidebar .accordion-menu li.droplink', function () {
            var flyout = this.querySelector(':scope > ul.sub-menu');
            if (!flyout) {
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
