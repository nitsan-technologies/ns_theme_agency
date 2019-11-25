# TypoScript for rendering in frontend
tt_content.gridelements_pi1.20.10.setup {
	# 1 < lib.gridelements.defaultGridSetup
	# 1 {
 #        cObject = FLUIDTEMPLATE
 #        cObject {
 #            file = typo3conf/ext/ns_theme_agency/Resources/Private/Extensions/Grid/Twocolumn.html
 #        }
 #    }

 #    2 < lib.gridelements.defaultGridSetup
 #    2 {
 #        cObject = FLUIDTEMPLATE
 #        cObject {
 #            file = typo3conf/ext/ns_theme_agency/Resources/Private/Extensions/Grid/ThreeColumn.html
 #        }
 #    }

    2 < lib.gridelements.defaultGridSetup
    2 {
        cObject = FLUIDTEMPLATE
        cObject {
            file = typo3conf/ext/ns_theme_agency/Resources/Private/Extensions/Grid/Container.html
        }
    }

    1 < lib.gridelements.defaultGridSetup
    1 {
        cObject = FLUIDTEMPLATE
        cObject {
            file = typo3conf/ext/ns_theme_agency/Resources/Private/Extensions/Grid/ThreeColumn.html
        }
    }
}