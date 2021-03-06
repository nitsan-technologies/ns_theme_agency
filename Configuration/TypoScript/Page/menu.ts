// Draw main menu
menu.main >
menu.main = HMENU
menu.main {
	
	special = directory
	special.value = {$ns_basetheme.website.settings.main_menu}
		
	1 = TMENU
	1 {
		wrap = <ul class="navbar-nav text-uppercase ml-auto">|</ul>
		expAll = 1
		noBlur = 1
		
		NO = 1
		NO {
			before.wrap = <li class="nav-item menu-{field:uid}">|
			before.wrap.insertData = 1
			ATagTitle {
				field = title
				fieldRequired = nav_title
			}
			stdWrap.cObject = COA
			stdWrap.cObject {
				10 = TEXT
				10 {
					field = title
					wrap = |
					stdWrap.case = lower
					stdWrap.replacement {
						10 {
				            search.char = 32
				            replace.char = 45
				        }
					    15 {
					      	search = /
					      	replace = 
					    	}
						}
					stdWrap.noTrimWrap = |<a href="#|"|
					stdWrap.noTrimWrap.insertData = 1
				}
				20 < .10
				20.stdWrap.noTrimWrap = | data-hash="#|" class="nav-link js-scroll-trigger">{field:title}</a>|
				20.stdWrap.noTrimWrap.insertData = 1
				
			}
			doNotLinkIt = 1
			after.wrap = |</li>			
		}
		
		IFSUB < .NO
		IFSUB {
			wrapItemAndSub.insertData = 1
			wrapItemAndSub = <li class="nav-item">|</li>
		}	
	}
	
	2 < .1
	2.wrap = <ul>|</ul>
	2.NO.wrapItemAndSub = <li>|</li>
		
	3 < .2	
	// 3.NO.doNotLinkIt = 1 |*| 0 |*| 0
}
menu.footermenu = HMENU
menu.footermenu {
	special = directory
	special.value = {$ns_basetheme.website.settings.footer_menu}
	1 = TMENU
	1 {
		wrap = <ul class="list-inline quicklinks">|</ul>
		expAll = 1
		noBlur = 1
		
		NO = 1
		NO {
			wrapItemAndSub = <li class="list-inline-item">|</li>
		}
	}
	
}