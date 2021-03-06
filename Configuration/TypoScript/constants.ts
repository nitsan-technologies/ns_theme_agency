# Let's define some constants for global configuration
ns_basetheme.website.settings.logo_width >
ns_basetheme.website.settings.logo_height >
ns_basetheme.website.settings.logo_text >
ns_theme_agency {
	website {
		settings {
			#cat = ns_basetheme/100/06; type=string; label=Logo Image Path
			logoImage = 
			#cat = ns_basetheme/120/06; type=string; label=LO
			twitter = 
			#cat = ns_basetheme/120/06; type=string; label=Twitter Link
			twitter = 
			#cat = ns_basetheme/120/07; type=string; label=Facebook Link
			facebook = 
			#cat = ns_basetheme/120/08; type=string; label=LinkedIn Link
			linkedin = 
			
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
