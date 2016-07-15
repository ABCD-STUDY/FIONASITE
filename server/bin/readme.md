### Series Classification

As DICOM images arrive they are sorted into different series. Each DICOM images header is evaluated by a system service processSingleFile.py that is using classification rules defined in classifyRules.json. Here an example of the structure of these rules:

```
{ 
    "type" : "GE",
    "id" : "GEBYMANUFACTURER",
    "description" : "This scan is from GE",
    "rules" : [
      { 
        "tag": [ "0x08", "0x70"],
        "value": "^GE MEDICAL"
      }
    ]  
}
```

The DICOM series in this rule will be assigned the type "GE" if all entries in the rules section match. In this case the rules section contains a single rule that matches the DICOM tag 0008,0070 to the regular expression "^GE MEDICAL" (starts with the string GE MEDICAL). The default operator in these cases is "operator": "regexp" (does not have to be specified). DICOM tags can also be referenced by their DICOM name ("tag": [ "ImagesInAcquisition" ]).

If more than one rule is listed in "rules" each rule has to match for the current type.

Implicit to the above rule is that the comparison of the tag value is done by a regular expression. Other comparison operations can be selected by specifying operators. Here an example that compares the tag with a numeric value.

```
{
   "type" : "mosaic",
   "description": "Siemens Mosaic format",
   "id" : "MOSAIC",
   "rules": [
       {
          "tag": [ "0x08", "0x08" ],
          "value": "MOSAIC",
          "operator": "contains"
       },
       {
          "rule": "SIEMENSBYMANUFACTURER"
       }
   ]
}
```

The operator "contains" in this case performs a string search. If the value "MOSAIC" appears anywhere in the string the rule will match. The rules for the type "mosaic" also contain a reference to another rule's id "SIEMENSBYMANUFACTURER" (id's have to be unique). Numerical comparisons are done with the operators "==", "!=", "<", and ">". The operator "exists" can be used to test of a tag exists.

Another operator that can be used is "approx" with an additional "approxLevel" tag. In this case the value has to be suffiently close given the approximate level to match:

```
{
   "type" : "sagittal",
   "description": "A sagittal scan",
   "id": "ORIENTATIONSAG",
   "rules" : [
     { "tag":     [ "0x20","0x37" ],
       "value":   [0,1,0,0,0,-1],
       "operator": "approx",
       "approxLevel": "0.0004"
     }
   ]
}
```

In case a rule needs to be negated you can add the key "negate" with the value "yes". Here an example that matches an oblique scan by negating rules for axial, coronal and sagittal:

```
{
    "type" : "oblique",
    "description": "Neither coronal, sagittal nor axial",
    "check": "SeriesLevel",
    "rules": [
        {
                 "tag": [ "ClassifyType" ],
                 "value": "axial",
                 "operator": "contains",
                 "negate": "yes"
        },
        {
                 "tag": [ "ClassifyType" ],
                 "value": "coronal",
                 "operator": "contains",
                 "negate": "yes"
        },
        {
                 "tag": [ "ClassifyType" ],
                 "value": "sagittal",
                 "operator": "contains",
                 "negate": "yes"
        }
    ]
}
```

In the above example types already classified by previous rules (sagittal) are used to calculate and add further types (oblique).

Note: There is currently no get access to sub-tags. Arrays are represented as single strings so regular expressions can be used to parse them.