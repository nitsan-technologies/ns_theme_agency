jQuery('.container.bg-light').parent('section').addClass('bg-light');

(function ($) {
  function movePortfolioModalsToBody() {
    document.querySelectorAll('.portfolio-modal').forEach(function (modal) {
      if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
      }
    });
  }

  function hidePortfolioModal(modal) {
    if (!modal) {
      return;
    }
    if (typeof $(modal).modal === 'function') {
      $(modal).modal('hide');
      return;
    }
    modal.classList.remove('show');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
      backdrop.remove();
    });
  }

  $(function () {
    movePortfolioModalsToBody();

    $(document).on('click', '.portfolio-modal [data-dismiss="modal"]', function (event) {
      event.preventDefault();
      hidePortfolioModal($(this).closest('.modal')[0]);
    });

    $('ul li a').click(function () {
      $('li a').removeClass('active');
      $(this).addClass('active');
    });
  });
})(jQuery);