
$(function(){
    $(".contact-form .check-styled input").change(function(e) {
        e.preventDefault();


        if($(".contact-form .check-styled input:checked").length) {
            $(".contact-form .check-styled").addClass("is-checked");
        } else {
            $(".contact-form .check-styled").removeClass("is-checked");
        }
    });

    lightbox.option({
        'resizeDuration': 200,
        'wrapAround': true,
        'showImageNumberLabel': true,
        'albumLabel': 'Obrázek %1 z %2',
        closeButtonLabel: 'Zavřít',
    });

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

    scrollPage();
    //initMapTwo();
});


function initMap(){
    if ($("#searchMap").length) {
        var map;

        var branches = [];
        var locations = [];

        $( ".map-select-box a.dropdown-item" ).each(function( index ) {
            var id = $(this).attr("data-id"),
                lat = $(this).attr("data-lat"),
                lng = $(this).attr("data-lng"),
                street = $(this).attr("data-street"),
                city = $(this).attr("data-city"),
                zip = $(this).attr("data-zip"),
                name = $(this).text();


            branches[id] = {lat: parseFloat(lat), lng: parseFloat(lng)};
            locations.push(["<div class=\"map-item\"><div class= \"map-item-text map-item-text-head\">"+name+"</div><p>"+street+",<br/> "+city+" "+zip+"</p></div>", lat, lng]);
        });


        map = new google.maps.Map(document.getElementById("searchMap"), {
            center: { lat: parseFloat(locations[0][1]), lng: parseFloat(locations[0][2])},
            zoom: 15,
            panControl: false,
            zoomControl: true,
            mapTypeControl: false,
            scaleControl: false,
            streetViewControl: true,
            overviewMapControl: false,
            rotateControl: false,
            fullscreenControl: false,
            icon: '/images/front/map-pin.svg',
        });

        var infowindow = new google.maps.InfoWindow();

        var marker, i;
        var gmarkers = [];

        for (i = 0; i < locations.length; i++) {

            marker = new google.maps.Marker({
                position: new google.maps.LatLng(locations[i][1], locations[i][2]),
                map: map,
                icon: '/images/front/map-pin.svg'
            });

            google.maps.event.addListener(marker, 'click', (function (marker, i) {
                return function () {
                    infowindow.setContent(locations[i][0]);
                    infowindow.open(map, marker);
                }
            })(marker, i));
            gmarkers.push(marker);
        };


        var links = document.querySelectorAll(".map-select-box .dropdown-item");

        links.forEach(function (link) {
            link.addEventListener("click", function (event) {
                event.preventDefault(); // Zabráníme výchozímu chování odkazu

                $(".map-select-box button").text($(this).text());
                $(".map-select-box a.dropdown-item").removeClass("d-none");
                $(this).addClass("d-none");

                var selectedBranch = link.getAttribute("href").replace("#p","");
                var branchLocation = branches[selectedBranch];
                if (branchLocation && !isNaN(branchLocation.lat) && !isNaN(branchLocation.lng)) {
                    map.setCenter({lat: branchLocation.lat, lng: branchLocation.lng});
                    map.setZoom(14);
                }
            });
        });
    }
}