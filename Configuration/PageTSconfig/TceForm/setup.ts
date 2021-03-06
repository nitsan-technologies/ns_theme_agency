# Remove default custom elements from EXT:ns_basetheme 
mod.wizards.newContentElement.wizardItems.extra {
  show := removeFromList(ns_imageteaser, ns_record, ns_slider)
}
TCEFORM.tt_content {
    header_layout {
        altLabels {
            1 = h1
            2 = h2
            3 = h3
            4 = h4
            5 = h5
        }
    }  
    layout {
        types {
            text {
            	altLabels.1 = Container Width
                removeItems = 2,3
            }
        }
    } 
}
