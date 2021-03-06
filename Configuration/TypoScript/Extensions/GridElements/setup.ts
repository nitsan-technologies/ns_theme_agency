# TypoScript for rendering in frontend
tt_content.gridelements_pi1.20.10.setup {

    nsBase1Col < lib.gridelements.defaultGridSetup
    nsBase1Col {
        cObject = FLUIDTEMPLATE
        cObject {
            file = typo3conf/ext/ns_theme_agency/Resources/Private/Extensions/Grid/Container.html
        }
    }
    # Three column grid container
    nsBase3Col < lib.gridelements.defaultGridSetup
    nsBase3Col {
        cObject = FLUIDTEMPLATE
        cObject {
            file = typo3conf/ext/ns_theme_agency/Resources/Private/Extensions/Grid/ThreeColumn.html
        }
    }
}