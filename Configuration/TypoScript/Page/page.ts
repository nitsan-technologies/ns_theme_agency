# Grab all the constant
plugin {
    ns_theme_agency {
        settings {
           logo = {$ns_basetheme.website.settings.logo}
           rootpage = {$ns_basetheme.website.settings.rootpage}
           facebook = {$ns_theme_agency.website.settings.facebook}
           twitter = {$ns_theme_agency.website.settings.twitter}
           linkedin = {$ns_theme_agency.website.settings.linkedin}
           logoImage = {$ns_theme_agency.website.settings.logoImage}
        }
    }
}

// Initiate Page Object
page = PAGE
page {
    // Setup favion
    shortcutIcon = {$ns_basetheme.website.settings.favicon}

    // Set viewport
    meta {
        viewport = width=device-width, initial-scale=1, shrink-to-fit=no
    }


    // Initiate all the css-together
    includeCSS {
        10 >
        20 >
        50 = typo3conf/ext/ns_theme_agency/Resources/Public/vendor/bootstrap/css/bootstrap.min.css
        60 = typo3conf/ext/ns_theme_agency/Resources/Public/vendor/fontawesome-free/css/all.min.css
        70 = https://fonts.googleapis.com/css?family=Montserrat:400,700
        80 = https://fonts.googleapis.com/css?family=Kaushan+Script
        90 = https://fonts.googleapis.com/css?family=Droid+Serif:400,700,400italic,700italic
        100 = https://fonts.googleapis.com/css?family=Roboto+Slab:400,100,300,700
        110 = typo3conf/ext/ns_theme_agency/Resources/Public/css/agency.min.css
        120 = typo3conf/ext/ns_theme_agency/Resources/Public/css/custom.css
    }

    // Initiate all the js-together
    includeJSFooter {
        10 = typo3conf/ext/ns_theme_agency/Resources/Public/vendor/jquery/jquery.min.js
    }

    includeJS{
        10 = typo3conf/ext/ns_theme_agency/Resources/Public/vendor/jquery/jquery.min.js
        20 = typo3conf/ext/ns_theme_agency/Resources/Public/vendor/bootstrap/js/bootstrap.bundle.min.js
        50 = typo3conf/ext/ns_theme_agency/Resources/Public/vendor/jquery-easing/jquery.easing.min.js
        60 = typo3conf/ext/ns_theme_agency/Resources/Public/js/jqBootstrapValidation.js
        80 = typo3conf/ext/ns_theme_agency/Resources/Public/js/agency.min.js
        90 = typo3conf/ext/ns_theme_agency/Resources/Public/js/custom.js
    }
    
    10 = FLUIDTEMPLATE
    10 {
        layoutRootPath = {$ns_theme_agency.website.paths.layoutRootPath}
        partialRootPath = {$ns_theme_agency.website.paths.partialRootPath}
        templateRootPath = {$ns_theme_agency.website.paths.templateRootPath}

        // Let's automatically choose backend layout and template
        file.stdWrap.cObject = CASE
        file.stdWrap.cObject {
            key.data = levelfield:-1, backend_layout_next_level, slide
            key.override.field = backend_layout
            
            default = TEXT
            default.value = {$ns_theme_agency.website.paths.templateRootPath}Default.html

            pagets__content = TEXT
            pagets__content.value = {$ns_theme_agency.website.paths.templateRootPath}Content.html
        }
        settings < plugin.ns_theme_agency.settings
    }
}

# Get default content
lib {
    headerContent < lib.content
    headerContent.select.where = colPos = 10

    footerContent < lib.content
    footerContent.select.where = colPos = 1
    footerContent.slide = -1

    scrolling = CONTENT
    scrolling {
        table = pages
        select {
            pidInList = {$ns_basetheme.website.settings.main_menu}
            where = doktype = 1 OR doktype = 3 AND nav_hide = 0
            orderBy = sorting ASC
        }
        renderObj = COA
        renderObj {
            5 = TEXT
            5 {
                field = title
                htmlSpecialChars = 1
                wrap = <section id="|">
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
            }

            20 = CONTENT
            20 {
            table = tt_content
            select {
              languageField = sys_language_uid
              pidInList.field = uid
              orderBy = sorting
              where = colPos = 0
            }
            stdWrap.wrap = |</section>
            stdWrap.wrap.insertData = 1
            }
        }
    }
}

# Set copyright
lib.copyright >
lib.copyright = COA
lib.copyright {
    stdWrap.wrap = |

    1 = TEXT
    1.current = 1
    1.strftime = %Y
    1.wrap = &copy;&nbsp;|&nbsp;

    2 = TEXT
    2.value = {$ns_basetheme.website.settings.copyright}
    2.wrap = |
}

# <body> class based on backend_layout
page.bodyTagCObject {
    wrap = <body class="|" id="page-top">

    10 = COA
    10 {
        # layout
        50 = CASE
        50 {
            key.data = levelfield:-1, backend_layout_next_level, slide
            key.override.field = backend_layout

            default = TEXT
            default.value = default

            pagets__content = TEXT
            pagets__content.value = content
        }
    }
}