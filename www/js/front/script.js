$(document).ready(function () {

  $('.header-menu-toggle').click(function () {
    $(this).toggleClass('open');
    $(".header-menu").toggleClass("opening");
    $('.header-wrapper').toggleClass("open");
    setTimeout(function () {
      $(".header-menu").toggleClass("open");
    }, 100);
  });

  // Add smooth scrolling to all links
  $(".scroll-item").on('click', function (event) {
    // Make sure this.hash has a value before overriding default behavior
    if (this.hash !== "") {
        // Prevent default anchor click behavior
        // event.preventDefault();

        // Store hash
        var hash = this.hash;

        // Using jQuery's animate() method to add smooth page scroll
        // The optional number (800) specifies the number of milliseconds it takes to scroll to the specified area
        if ($('.header-menu-toggle').is(':hidden')) {
            var scrollValue = $(hash).offset().top;
        }
        else {
            $('.header-menu-toggle').removeClass('open');
            $('.header-menu').removeClass("opening");
            $(".header-menu").removeClass("open");
            var scrollValue = $(hash).offset().top - 90;
        }
        $('html, body').animate({
            scrollTop: scrollValue
        }, 800, function () {
            // Add hash (#) to URL when done scrolling (default click behavior)
            // window.location.hash = hash;
        });

    } // End if
});

  const swiper = new Swiper('#top-slider', {
    // Optional parameters
    autoplay: {
      delay: 2500,
      disableOnInteraction: false,
    },
    // Navigation arrows
    navigation: {
      nextEl: '.swiper-button-next',
      prevEl: '.swiper-button-prev',
    },

    // And if we need scrollbar
    scrollbar: {
      el: '.swiper-scrollbar',
    },
  });


  const swiper2 = new Swiper('#banner-swiper', {
    // Optional parameters
    autoplay: {
      delay: 2500,
      disableOnInteraction: false,
    },
    // If we need pagination
    pagination: {
      el: '.swiper-pagination',
    },
  });
});