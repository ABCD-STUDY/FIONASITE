function zoom(d, i) {
  var k = r / d.r / 2;
  x.domain([d.x - d.r, d.x + d.r]);
  y.domain([d.y - d.r, d.y + d.r]);

  var t = vis.transition()
      .duration(d3.event.altKey ? 7500 : 750);

  t.selectAll("circle")
      .attr("cx", function(d) { return x(d.x); })
      .attr("cy", function(d) { return y(d.y); })
      .attr("r", function(d) { return k * d.r; });

  t.selectAll("text")
      .attr("x", function(d) { return x(d.x); })
      .attr("y", function(d) { return y(d.y); })
      .style("opacity", function(d) { return k * d.r > 20 ? 1 : 0; });

  node = d;
  d3.event.stopPropagation();
}
var w = 960,
    h = 800,
    r = 720,
    x = d3.scale.linear().range([0, r]),
    y = d3.scale.linear().range([0, r]),
    node,
    root;

var pack = d3.layout.pack()
             .size([r, r])
             .value(function(d) { return d.size; });

var vis = d3.select("#circles").insert("svg:svg", "h2")
            .attr("width", w)
            .attr("height", h)
            .append("svg:g")
            .attr("transform", "translate(" + (w - r) / 2 + "," + (h - r) / 2 + ")");

function createCircles() {
   if (studyData.length == 0) {
      jQuery.getJSON('/php/series.php', function(data) {
         studyData = data;
         setTimeout(function() { createCircles(); }, 200);
      });
      return;
   }

   // add to studyData information like size (number of images)
   root = { "name": "", "children": [] };
   s = Object.keys(studyData);
   for (var i = 0; i < s.length; i++) {
      var n = { "name": studyData[s[i]][0].StudyDescription, "children": [] };
      // now add the series to this study
      for (var j = 0; j < Object.keys(studyData[s[i]]).length; j++) {
         m = { "name": studyData[s[i]][j].SeriesDescription, "children": [] };
         for (var k = 0; k < studyData[s[i]][j].ClassifyType.length; k++) {
            m.children.push( { "name": studyData[s[i]][j].ClassifyType[k], "size": studyData[s[i]][j].NumFiles } );
         } 
         n.children.push(m);
      }
      root.children.push( n );
   }

   node = root;

   var nodes = pack.nodes(root);

   vis.selectAll("circle")
      .data(nodes)
      .enter().append("svg:circle")
      .attr("class", function(d) { return d.children ? "parent" : "child"; })
      .attr("cx", function(d) { return d.x; })
      .attr("cy", function(d) { return d.y; })
      .attr("r", function(d) { return d.r; })
      .on("click", function(d) { return zoom(node == d ? root : d); });

   vis.selectAll("text")
      .data(nodes)
      .enter().append("svg:text")
      .attr("class", function(d) { return d.children ? "parent" : "child"; })
      .attr("x", function(d) { return d.x; })
      .attr("y", function(d) { return d.y; })
      .attr("dy", ".35em")
      .attr("text-anchor", "middle")
      .style("opacity", function(d) { return d.r > 20 ? 1 : 0; })
      .text(function(d) { return d.name; });

   d3.select(window).on("click", function() { zoom(root); });

}
