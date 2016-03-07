
var colors = {
      'SIEMENS':         '#edbd00',
      'mosaic':              '#367d85',
      'T2weighted':          '#97ba4c',
      'PhaseEncodeUD':       '#f5662b',
      'oblique':             '#3f3e47',
      'fallback':            '#9f9fa3'
};

function createBars() {
   if (studyData.length == 0) {
      jQuery.getJSON('/php/series.php', function(data) {
         studyData = data;
         setTimeout(function() { createBars(); }, 200);
      });
      return;
   }


    function label(node) {
        return node.name.replace(/\s*\(.*?\)$/, '');
    }
    function color(node, depth) {
        var id = node.id.replace(/(_score)?(_\d+)?$/, '');
        if (colors[id]) {
            return colors[id];
        } else if (depth > 0 && node.targetLinks && node.targetLinks.length == 1) {
            return color(node.targetLinks[0].source, depth-1);
        } else {
            return null;
        }
    }
    
    function nID( name, nodes ) {
        for (var k = 0; k < nodes.length; k++) {
            if (name == nodes[k]['id']) {
                return k;
            }
        }
	return -1;
    }
    
    root = { "nodes": [], "links": [] };
    s = Object.keys(studyData);
    for (var i = 0; i < s.length; i++) {
        // add a node for the patient and one for the node
        root.nodes.push( { "name": studyData[s[i]][0].StudyDescription, "id": studyData[s[i]][0].StudyInstanceUID } );
        foundPatient = false;
        for (var k = 0; k < root.nodes.length; k++) {
            if (root.nodes[k].id == studyData[s[i]][0].PatientID) {
                foundPatient = true;
                break;
            }
        }
        if (!foundPatient) { // add the patient ID from the study info
            root.nodes.push( { "name": studyData[s[i]][0].PatientID, "id": studyData[s[i]][0].PatientID } );
        }
        var li = { "target": nID(studyData[s[i]][0].StudyInstanceUID,root.nodes), "source": nID(studyData[s[i]][0].PatientID,root.nodes), "value": 0 };
        var totalImages = 0;
        // now add the series to this study
        for (var j = 0; j < Object.keys(studyData[s[i]]).length; j++) {
            root.nodes.push( { "name": studyData[s[i]][j].SeriesDescription, "id": studyData[s[i]][j].SeriesInstanceUID } );
            root.links.push( { "target": nID(studyData[s[i]][j].SeriesInstanceUID,root.nodes), 
			       "source": nID(studyData[s[i]][0].StudyInstanceUID,root.nodes), 
			       "value": studyData[s[i]][j].NumFiles } );
            totalImages = totalImages + studyData[s[i]][j].NumFiles;
            for (var k = 0; k < studyData[s[i]][j].ClassifyType.length; k++) {
                foundType = false;
                for (var l = 0 ; l < root.nodes.length; l++) {
                    if ( root.nodes[l].id == studyData[s[i]][j].ClassifyType[k]) {
                        foundType = true;
                        break;
                    }
                }
                if (!foundType) {
                    root.nodes.push( { "name": studyData[s[i]][j].ClassifyType[k], "id": studyData[s[i]][j].ClassifyType[k] } );
                }
                root.links.push( { "target": nID(studyData[s[i]][j].ClassifyType[k],root.nodes), 
				   "source": nID(studyData[s[i]][j].SeriesInstanceUID,root.nodes), 
				   "value":  studyData[s[i]][j].NumFiles / studyData[s[i]][j].ClassifyType.length } );
            } 
        }
	li.value = 20;
        root.links.push( li );
    }
    
    json = root;
    var chart = d3.select("#bars").append("svg").chart("Sankey.Path");
    chart
        .name(label)
        .colorNodes(function(name, node) {
            return color(node, 1) || colors.fallback;
        })
        .colorLinks(function(link) {
            return color(link.source, 4) || color(link.target, 1) || colors.fallback;
        })
        .nodeWidth(15)
        .nodePadding(10)
        .spread(true)
        .iterations(0)
        .draw(json);
}
