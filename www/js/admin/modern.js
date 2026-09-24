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

    // Makes .page-inner height same as .page-sidebar height
    var sidebarAndContentHeight = function () {
        var content = $('.page-inner'), sidebar = $('.page-sidebar'), body = $('body'), height, footerHeight = $('.page-footer').outerHeight(),
            pageContentHeight = $('.page-content').height();

        content.attr('style', 'min-height:' + sidebar.height() + 'px !important');

        if (body.hasClass('page-sidebar-fixed')) {
            height = sidebar.height() + footerHeight;
        }
        else {
            height = sidebar.height() + footerHeight;
            if (height < $(window).height()) {
                height = $(window).height();
            }
        }

        if (height >= content.height()) {
            content.attr('style', 'min-height:' + height + 'px !important');
        }
    };

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

});
