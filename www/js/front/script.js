$(document).ready(function () {

  // fixed "Landwind" navbar: mobile menu toggle, shadow once the page is scrolled
  var closeMenu = function () {
    $('.lw-toggle').attr('aria-expanded', 'false');
    $('.lw-menu').removeClass('lw-open');
  };
  $('.lw-toggle').on('click', function () {
    var open = $(this).attr('aria-expanded') !== 'true';
    $(this).attr('aria-expanded', open ? 'true' : 'false');
    $('.lw-menu').toggleClass('lw-open', open);
  });
  $('.lw-menu').on('click', 'a', closeMenu);
  var headerShadow = function () {
    $('#lw-header').toggleClass('lw-scrolled', $(window).scrollTop() > 4);
  };
  $(window).on('scroll', headerShadow);
  headerShadow();

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
        if (!$(hash).length) {
            return;
        }
        closeMenu();
        var scrollValue = $(hash).offset().top - ($('#lw-header').outerHeight() || 0);
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