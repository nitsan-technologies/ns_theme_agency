(function($) {
  "use strict"; // Start of use strict

  function getHashTarget(hash) {
    if (!hash || hash.length < 2) {
      return $();
    }
    var id = decodeURIComponent(hash.slice(1));
    var element = document.getElementById(id);
    if (element) {
      return $(element);
    }
    try {
      return $('[name="' + id.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
    } catch (e) {
      return $();
    }
  }

  function closeResponsiveMenu() {
    $('.navbar-collapse').collapse('hide');
  }

  // Smooth scrolling and always close responsive menu on nav click
  $('a.js-scroll-trigger').click(function() {
    var href = this.getAttribute('href') || '';
    if (href.indexOf('#') !== -1 && href !== '#') {
      if (location.pathname.replace(/^\//, '') === this.pathname.replace(/^\//, '') && location.hostname === this.hostname) {
        var target = getHashTarget(this.hash);
        if (target.length) {
          $('html, body').animate({
            scrollTop: (target.offset().top - 54)
          }, 1000, "easeInOutExpo");
        }
      }
    }
    closeResponsiveMenu();
  });

  // Activate scrollspy to add active class to navbar items on scroll
  $('body').scrollspy({
    target: '#mainNav',
    offset: 56
  });

  // Collapse Navbar
  var navbarCollapse = function() {
    if ($("#mainNav").offset().top > 100) {
      $("#mainNav").addClass("navbar-shrink");
    } else {
      $("#mainNav").removeClass("navbar-shrink");
    }
  };
  // Collapse now if page is not at top
  navbarCollapse();
  // Collapse the navbar when page is scrolled
  $(window).scroll(navbarCollapse);

  // Hide navbar when modals trigger
  $('.portfolio-modal').on('show.bs.modal', function(e) {
    $('.navbar').addClass('d-none');
  })
  $('.portfolio-modal').on('hidden.bs.modal', function(e) {
    $('.navbar').removeClass('d-none');
  })


})(jQuery); // End of use strict
