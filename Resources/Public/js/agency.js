(function($) {
  "use strict"; // Start of use strict

  var scrollOffset = 54;

  function getScrollTarget(hash) {
    if (!hash || hash.length < 2) {
      return $();
    }
    var id = decodeURIComponent(hash.slice(1));
    var element = document.getElementById(id);
    if (element) {
      return $(element);
    }
    if (window.CSS && CSS.escape) {
      return $('[name="' + CSS.escape(id) + '"]');
    }
    return $('[name="' + id.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]');
  }

  function scrollToHash(hash, animate) {
    var target = getScrollTarget(hash);
    if (!target.length) {
      return false;
    }
    var top = target.offset().top - scrollOffset;
    if (animate) {
      $('html, body').animate({
        scrollTop: top
      }, 1000, "easeInOutExpo");
    } else {
      $('html, body').scrollTop(top);
    }
    return true;
  }

  // Smooth scrolling using jQuery easing
  $('a.js-scroll-trigger[href*="#"]:not([href="#"])').click(function() {
    if (location.pathname.replace(/^\//, '') == this.pathname.replace(/^\//, '') && location.hostname == this.hostname) {
      if (scrollToHash(this.hash, true)) {
        return false;
      }
    }
  });

  // Scroll to hash on load (e.g. /#about)
  if (window.location.hash) {
    window.setTimeout(function() {
      scrollToHash(window.location.hash, false);
    }, 0);
  }

  // Closes responsive menu when a scroll trigger link is clicked
  $('.js-scroll-trigger').click(function() {
    $('.navbar-collapse').collapse('hide');
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
