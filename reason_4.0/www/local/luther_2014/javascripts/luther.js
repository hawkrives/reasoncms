// custom scripts for luther.edu

$(document).ready(function() {

	// GENERAL
	//////////////////////////////////////////////////

	// Add js class
	$('body').addClass('js');

	// Remove &nbsp; tags generated by the WYSIWYG. They get thrown in all over the place, and can really mess up styling.
	$('.fullPost p, .pageContent p, .blurb p, .announcement p').each(function() {
		var $this = $(this);
		$this.html($this.html().replace(/&nbsp;/g, ''));
	});

	// Give the first tab and tab item an active class
	$('#tabs .fragment-1').addClass('active');
	$('#tabs #fragment-1').addClass('active');

	// NAVIGATION
	//////////////////////////////////////////////////
	
	// MOBILE SEARCH
	// Open / close global search on mobile
	$('#mobile-nav a.search').click(function( event ) { // click the search icon
		$(this).toggleClass('open');
		$('#search-nav').toggleClass('open');
		event.preventDefault();
	});
	
	// MINISITE NAVIGATION
	// Open / close minisite navigation on mobile
	$('#navWrap a.toggle').click(function( event ) {  // click the hamburger
		$(this).toggleClass('open'); // Adds open class to button as a css hook
		$('#navWrap #minisiteNavigation').slideToggle('400'); // slides open nav
		$('#navWrap .subNavElements').slideToggle('400'); // slides open sub nav
		event.preventDefault();
	});

	// Animate show/hide of children pages
    $('li.navListItem.accordion > a').click(function( event ) { // click the menu item

		var item = $(this).parent();

		// open/closed classes for no-js
		if(item.hasClass('closed')) {
			item.removeClass('closed');
			item.addClass('open');
		} else {
			item.removeClass('open');
			item.addClass('closed');
		}

		// slide open  
		item.children('ul').slideToggle('400');

		event.preventDefault();
    });

    // We need to set navLists on open accorions to display:block so that they toggle correctly
    $('li.navListItem.accordion.open ul.navList').css('display', 'block');

    // TABLES
	//////////////////////////////////////////////////
	$('#contentAndRelated table').addClass('responsive');		

    // CALL TO ACTION
	//////////////////////////////////////////////////

    // Add analytics tracking to call to action buttons
    $("a[class^=cta-]").each(function(index) {
    	if ((label = $(this).attr("class").match(/^cta\-([A-Za-z0-9_\-]+)/)[1]) != "button") {
    		$(this).attr("onclick", "_gaq.push(['trackEvent', 'call-to-action', 'click', '" + $(location).attr('pathname') + "button" + label + "']);");
    	}
    });		

});