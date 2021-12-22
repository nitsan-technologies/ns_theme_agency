jQuery('.container.bg-light').parent('section').addClass('bg-light');
$(document).ready(function(){
  $('ul li a').click(function(){
    $('li a').removeClass("active");
    $(this).addClass("active");
});
});