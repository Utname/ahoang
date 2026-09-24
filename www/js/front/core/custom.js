var body;
var globalInitialized = false;

$(function () {
    body = $("body");

    globalInitAll();
});


var globalInitAll = function () {
    if (!globalInitialized) {
        globalInitOnce();

        globalInitialized = true;
    }

    globalInitAlways();
}


var globalInitOnce = function () {
    initAjax();
    initToggles();
    fixBoxHeights();
    initMaps();
    initSmoothScroll();
    initIndexBanner();
    timeline();
    locationHash();
    autoScrollSendingForm();
    scrollAnimate();
    activeMenu();
    scrollPage();
}


var globalInitAlways = function () {
    showFlashMessages();
    fixLinks();
    initBasicAntiSpamProtection();
}


$(window).resize(function(){
    fixBoxHeights();
});


/**
 * FLASH MESSAGES
 */
var showFlashMessages = function () {
    $("div.flash-messages").each(function (i) {
        var text = $(this).html();

        if ($(this).hasClass("info") || $(this).hasClass("success")) {
            alertify.success(text);
        }
        if ($(this).hasClass("error") || $(this).hasClass("danger")) {
            alertify.error(text);
        }
    })
}


/**
 * AJAX
 */
var initAjax = function () {
    body.on('click', "a.ajax", function (event) {
        event.preventDefault();

        if (!$(this).hasClass('with-confirm')) {
            ajaxGet(this.href);
        }
    });

    body.on('submit', "form.ajax", function (event) {
        event.preventDefault();

        ajaxPost(this.action, $(this).serialize());
    });

    body.on('change', ".change-to-ajax", function () {
        ajaxGet(String($(this).data('url')).replace('-1', $(this).val()));
    });
}



/**
 * AJAX
 */
var ajaxGet = function (href, callback) {
    $.ajax({
        type: 'get',
        url: href,
        cache: false,
        success: function (payload) {
            ajaxCallback(payload, callback);
        }
    });
}


var ajaxPost = function (action, data, callback) {
    $.post(action, data, function (payload) {
        ajaxCallback(payload, callback);
    });
}


var ajaxCallback = function (payload, callback) {
    if (payload.redirect) {
        location.href = payload.redirect;
    }

    if (payload.snippets) {
        for (var i in payload.snippets) {
            if (String(i).indexOf('Append') !== -1) {
                $('#' + i).append(payload.snippets[i]);
            }
            else {
                $('#' + i).html(payload.snippets[i]);
            }

            $('#' + i + ' form').each(function () {
                Nette.initForm(this);
            });
        }
    }

    if (payload.forms) {
        for (var f in payload.forms) {
            if (payload.forms[f]) {
                for (var i in payload.forms[f]) {
                    console.log("#frm-" + f + "-" + i);
                    $("#frm-" + f + "-" + i).val(payload.forms[f][i]);
                }
            }
        }
    }

    if (payload.urlParameter) {
        window.history.replaceState(null, null, payload.urlParameter);
    }

    if (payload.formInputFocus) {
        var field = $("input[name=" + payload.formInputFocus + "]");
        if (field.length) {
            field.focus();
        }
    }

    if (payload.shakeCart) {
        $(".header-cart").effect("shake", {
            times: 2,
            distance: 3
        });
    }

    if (callback) {
        callback(payload);
    }

    fixBoxHeights();
    initAll();
}


var locationHash = function(){
    if (location.hash) {
        var hasLocation = location.hash.match(/#([a-z0-9|-]+)/gi)[0];

        if($(hasLocation).length) {
            $('html, body').animate({
                scrollTop: $($(hasLocation)).offset().top
            }, 2000);
        }
    }
}

var timeline = function () {
    if($(".timeline").length){
        $(".timeline .panel a").on("click", function(e){
            var $selector = $(this).closest(".panel");

            if(!$selector.find(".panel-collapse").hasClass("in")){
                var image = $selector.data("image"),
                    imageTitle = $selector.data("image-title");

                $selector.closest(".timeline").find(".about-img img").attr({src:image, alt:imageTitle});
            }
        });
    }
}

/**
 * MAPS
 */
var initMaps = function () {
    if($('.map-block').length) {
        if (typeof window.stylesMap === "undefined") {
            window.stylesMap = [];
        }

        $('.map-block').each(function (e) {
            var mapContainer = $(this);
            if (mapContainer.length) {
                if (mapContainer.data("height")) {
                    mapContainer.css('height', mapContainer.data("height") + 'px');
                }

                var gps = mapContainer.data("gps");
                var zoom = mapContainer.data("zoom");
                var gpsParts = gps.split(',');

                var mapCenter = new google.maps.LatLng(parseFloat(gpsParts[0]), parseFloat(gpsParts[1]));
                var mapOptions = {
                    center: mapCenter,
                    zoom: zoom,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    styles: window.stylesMap,
                    scrollwheel: false
                };

                var map = new google.maps.Map(mapContainer[0], mapOptions);

                var mapMarker = new google.maps.Marker({
                    position: mapCenter,
                    map: map
                });

                var mapInfoWindow = new google.maps.InfoWindow({
                    content: mapContainer.data("info-window"),
                });

                mapInfoWindow.open(map, mapMarker);
            }
        })
    }
}


/**
 * PICKERS
 */
var initDatePickers = function () {
    $(".date-picker").datepicker({
        language: "cs",
        autoclose: true,
        format: 'dd.mm.yyyy',
        weekStart: 1,
    });
}


/**
 * TOGGLES
 */
var initToggles = function () {
    $("body").on('change', ".toggle-trigger", function () {
        var trigger = $(this);
        update(trigger);
    });

    $(".toggle-trigger").each(function (e) {
        var trigger = $(this);
        update(trigger);
    })

    function update(trigger) {
        if (trigger.data('target')) {
            $(trigger.data('target')).each(function () {
                var showOn = String($(this).data('show-on')).split('|');
                var value = trigger.is(':checkbox') ? (trigger.prop('checked') ? '1' : '0') : trigger.val();

                if (showOn.indexOf(value) !== -1) {
                    $(this).slideDown(200);
                }
                else {
                    $(this).slideUp(200);
                }
            });
        }
    }
}


/**
 * BOX HEIGHTS
 */
var fixBoxHeights = function(){

    $('.fix-height').each(function () {
        var maxheight = 0;
        var maxheight2 = 0;
        var maxheight3 = 0;

        $(this).find('.height')
            .each(function () {
                $(this).css("height", "auto");

                if ($(this).height() > maxheight) {
                    maxheight = $(this).height();
                }
            })
            .each(function () {

                if($(this).hasClass("line-height")){
                    $(this).find("p").css("line-height", maxheight+"px");
                    $(this).find("p").height(maxheight);
                }else{
                    $(this).height(maxheight);
                }
            })

        $(this).find('.height-2')
            .each(function () {
                $(this).css("height", "auto");

                if ($(this).height() > maxheight2) {
                    maxheight2 = $(this).height();
                }
            })
            .each(function () {
                $(this).height(maxheight2);
            })

        $(this).find('.height-3')
            .each(function () {
                $(this).css("height", "auto");

                if ($(this).height() > maxheight3) {
                    maxheight3 = $(this).height();
                }
            })
            .each(function () {
                $(this).height(maxheight3);
            })
    })
}


/**
 * FIX LINKS
 */
var fixLinks = function(){
    $('a').each(function(e){
        if($(this).attr('href') &&
            !$(this).attr('href').startsWith("/") &&
            !$(this).attr('href').startsWith("http") &&
            !$(this).attr('href').startsWith("mailto:") &&
            !$(this).attr('href').startsWith("tel:") &&
            !$(this).attr('href').startsWith("#") &&
            !$(this).attr('href').startsWith("javascript:")){
            $(this).attr('href', '//'+$(this).attr('href'));
        }
        if($(this).attr('href') && $(this).attr('href').indexOf('//') >= 0 && $(this).attr('href').indexOf(location.hostname) == -1 && !$(this).attr('target')){
            $(this).attr('target', '_blank');
        }
    })
}

/**
 * ANTISPAM PROTECTION
 */
var initBasicAntiSpamProtection = function(){

    // Auto insert IDForm
    if($("form script[data-formId]").length){
        $("form script[data-formId]").each(function( index ) {
            $(this).closest("form").addClass("form-"+$(this).attr("data-formId"));
        });
    }

    $('#basic_asp_button').on("mouseover", function () {
        var submit = $(this);
        var randNumber1 = randomInteger(1, 100);
        var randNumber2 = randomInteger(1, 100);
        var number3 = randNumber1 + randNumber2;
        submit.closest('form').find('input[name="system"]').val(randNumber1+'-'+randNumber2+'-'+number3);
    });

    function randomInteger(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    //invisible recaptcha
    $(document).on('click', '#recaptcha_invisible_asp_button', function(e){
        e.preventDefault();
        console.log(Nette.validateForm($(this).closest('form')[0]));
        if(Nette.validateForm($(this).closest('form')[0])){
            grecaptcha.execute();
        }
    })
}

/**
 * ANTISPAM PROTECTION OLD ASP
 */
var initAntiSpamProtection = function(){
    $('.asp-button').on("mouseover", function () {
        var submit = $(this);
        if(submit.hasClass('asp-button')){
            var formElm;
            var tempElm = submit;
            for(var i = 0; i < 999; i++){
                tempElm = tempElm.parent()
                if(tempElm.hasClass('asp-form')){
                    break;
                }
            }
            var doElm = tempElm.find("input[name='_do']");
            if(doElm.length > 0){
                var value = doElm.val();
                value = value.replace('asp_', '');
                doElm.val(value);

                submit.removeClass('asp-button');
            }
        }

    });

    //invisible recaptcha
    $(document).on('click', '.invisible-recaptcha-button', function(e){
        e.preventDefault();
        console.log(Nette.validateForm($(this).closest('form')[0]));
        if(Nette.validateForm($(this).closest('form')[0])){
            grecaptcha.execute();
        }
    })
}


/**
 * SMOOTH SCROLL
 */
var initSmoothScroll = function(){
    $(document).on('click', '.smooth-scroll, a[href^="#block-"]', function(){
        $('html, body').animate({
            scrollTop: $($(this).attr('href')).offset().top
        }, 1000);
    });
}

var autoScrollSendingForm = function(){
    if($("form.auto-scroll").length) {
        var fid = getUrlParameter('_fid');

        if (fid) {
            var form = $('form.auto-scroll[action*="' + fid + '"]');
            var customOffset = $("form.auto-scroll").attr("data-offset") ? form.offset().top - $("form.auto-scroll").attr("data-offset") : form.offset().top;

            // Scroll na formulář, pokud byl nalezen
            if (form.length > 0) {
                $('html, body').animate({
                    scrollTop: customOffset
                }, 500);
            }
        }
    }
}

var scrollAnimate = function(){

    $(document).on("click",".home-page, .viewport a.link", function(e){
        e.preventDefault();

        if($("div[data-url='"+$(this).attr('href')+"']").length && $(this).attr('href').startsWith("http", 4) != "http") {
            history.pushState({}, null, $(this).attr("href"));
            goToPage("div[data-url='"+$(this).attr('href')+"']");
        }else if($(this).attr('href') == $(".home-page").attr('href')){
            if($("#home").length) {
                goToPage("#home");
            }else{
                location.href = $(".home-page").attr('href');
            }
        }else if(
            $(this).attr('href').startsWith("mailto:") ||
            $(this).attr('href').startsWith("tel:")){
            location.href=$(this).attr("href");
        }else{
            if($(this).attr("target") == "_self"){
                location.href=$(this).attr("href");
            }else {
                window.open($(this).attr("href"), "_blank");
            }
        }
    });

    $(document).on('click', 'a[href^="#"]', function(e) {
        e.preventDefault();
        $("html, body").animate({scrollTop:  $(hash).offset().top - 50}, 1000);
    });

    $(".go-to-element").on("click", function(e){
        e.preventDefault();

        $("html, body").animate({ scrollTop: $($(".go-to-element").attr("href")).offset().top-50 }, 1000);
    });
}

var scrollPage = function () {
    window.scrollPageRun = false;

    if($("#home").length) {
        $(window).on('scroll', function (e) {
            if (!window.scrollPageRun) {
                actionScroll();
            }
        });
    }

    $(document).on("click",".home-page, .viewport a.link", function(e){
        e.preventDefault();

        if($("div[data-url='"+$(this).attr('href')+"']").length && $(this).attr('href').startsWith("http", 4) != "http") {
            history.pushState({}, null, $(this).attr("href"));
            goToPage("div[data-url='"+$(this).attr('href')+"']");
        }else if($(this).attr('href') == $(".home-page").attr('href')){
            if($("#home").length) {
                goToPage("#home");
            }else{
                location.href = $(".home-page").attr('href');
            }
        }else if(
            $(this).attr('href').startsWith("mailto:") ||
            $(this).attr('href').startsWith("tel:")){
            location.href=$(this).attr("href");
        }else{
            if($(this).attr("target") == "_self"){
                location.href=$(this).attr("href");
            }else {
                window.open($(this).attr("href"), "_blank");
            }
        }
    });


    if($("div[data-url='"+window.location.pathname+"']").length){
        goToPage("div[data-url='"+window.location.pathname+"']");
    }

    function actionScroll(){
        if($(".navfake").length){
            return false;
        }

        window.scrollPageRun = true;

        window.setTimeout(function(){
            var ViewportElement = [];
            $(".viewport").each(function( index ) {
                if($(this).data('url') != '' && $(".header-menu a[href='"+$(this).data('url')+"']").length) {
                    if(isInViewport("#" + $(this).attr('id'))){
                        ViewportElement.push($(this).data('url'));
                    }
                }
            });

            $(".navbar .nav a").removeClass("active");

            if(typeof ViewportElement != "undefined" && typeof ViewportElement[0] != "undefined"){
                var hashUrl = "";
                if(window.location.hash){
                    hashUrl = window.location.hash;
                }



                history.pushState({}, null, ViewportElement[0]+hashUrl);
                $(".navbar .nav a[href='"+ViewportElement[0]+"']").addClass("active");
            }else{
                history.pushState({}, null, $(".home-page").attr('href') ? $(".home-page").attr('href') : "/");

                $(".navbar .nav a[href='/']").addClass("active")
            }

            window.scrollPageRun = false;
        }, 1000);

    }

    function goToPage(selector){
        $('html, body').animate({
            scrollTop: $(selector).offset().top
        }, 1000);
    }

    function isInViewport(element){
        var selector = document.querySelector(element),
            rect = selector.getBoundingClientRect(),
            elementViewPort = [];

        return rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight+($(element).height() / 2) || document.documentElement.clientHeight+($(element).height() / 2)) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth);
    }
}

function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}


!function(e){"function"==typeof define&&define.amd?define(["jquery"],e):"object"==typeof exports?module.exports=e(require("jquery")):e(jQuery)}(function(l){var i=/\+/g;function m(e){return g.raw?e:encodeURIComponent(e)}function x(e,n){var o=g.raw?e:function(e){0===e.indexOf('"')&&(e=e.slice(1,-1).replace(/\\"/g,'"').replace(/\\\\/g,"\\"));try{return e=decodeURIComponent(e.replace(i," ")),g.json?JSON.parse(e):e}catch(e){}}(e);return l.isFunction(n)?n(o):o}var g=l.cookie=function(e,n,o){if(1<arguments.length&&!l.isFunction(n)){if("number"==typeof(o=l.extend({},g.defaults,o)).expires){var i=o.expires,r=o.expires=new Date;r.setMilliseconds(r.getMilliseconds()+864e5*i)}return document.cookie=[m(e),"=",function(e){return m(g.json?JSON.stringify(e):String(e))}(n),o.expires?"; expires="+o.expires.toUTCString():"",o.path?"; path="+o.path:"",o.domain?"; domain="+o.domain:"",o.secure?"; secure":""].join("")}for(var t,c=e?void 0:{},u=document.cookie?document.cookie.split("; "):[],s=0,a=u.length;s<a;s++){var d=u[s].split("="),f=(t=d.shift(),g.raw?t:decodeURIComponent(t)),p=d.join("=");if(e===f){c=x(p,n);break}e||void 0===(p=x(p))||(c[f]=p)}return c};g.defaults={},l.removeCookie=function(e,n){return l.cookie(e,"",l.extend({},n,{expires:-1})),!l.cookie(e)}});

var initIndexBanner = function(){
    var banner = $('#modal-indexBanner');
    if(banner.length > 0){
        var id = banner.data('id');

        if(banner.data('type') == 1) { //INDEX_BANNERS_TYPE_SHOW_ONCE_PER_DAY
            if ($.cookie("banner_"+id) != "shown") {
                banner.modal('show');
                $.cookie("banner_"+id, "shown", { expires: 1, path: '/' });
            }
        }

        if(banner.data('type') == 2) { //INDEX_BANNERS_TYPE_SHOW_EVERY_VISIT
            if(!sessionStorage.seenIndexBanner){
                banner.modal('show');
                sessionStorage.seenIndexBanner = 1;
            }
        }
    }
}

var activeMenu = function(){
    if($("body").find("[data-active-menu]").length){
        var menu = $("body").find("[data-active-menu]");
        var selectClass = $(menu).attr("data-active-menu");

        if($(menu).find(selectClass+".active").length){
            $(menu).find(selectClass+".active").parents(selectClass).addClass("active");
        }
    }
}


$(function() {
    /*------ ScrollUp -------- */
    $.scrollUp({
        scrollText: '<i class="fal fa-chevron-up"></i>',
        easingType: 'linear',
        scrollSpeed: 900,
        animation: 'fade'
    });
})