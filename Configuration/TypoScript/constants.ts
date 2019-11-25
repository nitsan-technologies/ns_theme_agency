# Let's define some constants for global configuration
ns_theme_agency {
	website {
		settings {
			#cat = ns_theme_agency/website/settings/01; type=string; label=Twitter Link
			twitter = 
			#cat = ns_theme_agency/website/settings/02; type=string; label=Facebook Link
			facebook = 
			#cat = ns_theme_agency/website/settings/03; type=string; label=LinkedIn Link
			linkedin = 
			#cat = ns_theme_agency/website/settings/04; type=string; label=Google Analytics Id
            googleanalytics =
		}
		paths {
			#cat = ns_theme_agency/website/settings/01; type=string; label=Template Path
			templateRootPath = typo3conf/ext/ns_theme_agency/Resources/Private/Templates/

			#cat = ns_theme_agency/website/settings/02; type=string; label=Layouts Path
			layoutRootPath = typo3conf/ext/ns_theme_agency/Resources/Private/Layouts/

			#cat = ns_theme_agency/website/settings/03; type=string; label=Partials Path
			partialRootPath = typo3conf/ext/ns_theme_agency/Resources/Private/Partials/
		}
	}
}
