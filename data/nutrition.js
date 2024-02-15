var nutrition = {
    "metadata": {
	"doc": "Food Attribute Database",
	"author": "Dov Kruger",
	"contact": "dkruger@stevens.edu",
	"sources": ["www.hsph.harvard.edu/nutritionsource/",
		    "www.ncbi.nlm.nih.gov/books/NBK222310/pdf/Bookshelf_NBK222310.pdf",
		    "fdc.nal.usda.gov/"
		   ],
	"explanations": {
	    "AI" : "Adequate Intake, typically when RDA has not been sufficiently established",
	    "RDA": "Recommended Daily Allowance",
	    "UL": "Upper Level beyond which toxic effects are common",
	    "unit": "The unit in which all dosages for RDA, AI, UL are specified",
	    "class": "The kind of substance, categories vitamin, mineral, nutrient, antinutrient",
	    "vitamin":"A complex molecule discovered fundamental to life, required to prevent specific disease",
	    "mineral":"A simple element or compound required for life, though some in trace amounts",
	    "nutrient":"A material that appears to have distinct benefits for health though studies may not be definitive. Absence of the nutrient does not correlate with specific disease",
	    "antinutrient":"A chemical compound that binds to specific nutrients rendering them unavailable"	    
	},
    },
    "nutrients": [
	{
	    "name": "vA",
	    "long": "Vitamin A",
	    "alt": "retinol",
	    "RDA":  ["mcg",
		     "adult men", 900,
		     "adult women", 700,
		    ],
	    "UL": ["adults", 3000]
	},
	{
	    "name": "vB1",
	    "long": "vitamin B1",
	    "alt": "Thiamin",
	    "unit": "mg",
	    "RDA":  ["men 19+", 1.2,
		     "women 19+", 1.1,
		     "pregnant", 1.4,
		     "lactating", 1.4
		    ],
	    "UL": []
	},
	{
	    "name": "vB2",
	    "long": "vitamin B2",
	    "alt": "Riboflavin",
	    "unit": "mg",
	    "RDA":  ["men 19+", 1.3,
		     "women 19+", 1.1,
		     "pregnant", 1.4,
		     "lactating", 1.6
		    ],
	    "UL": []
	},
	{
	    "name": "vB3",
	    "long": "vitamin B3",
	    "alt": "niacin",
	    "unit": "mg",
	    "RDA":  ["men 19+", 16,
		     "women 19+", 14,
		     "pregnant", 18,
		     "lactating", 17		 
		    ],
	    "UL": ["adults 19+", 35]
	},
	{
	    "name": "vB5",
	    "long": "vitamin B5",
	    "alt": "Pantothenic Acid",
	    "unit": "mg",
	    "RDA":  [,
		    ],
	    "UL": []
	},
	{
	    "name": "vB6",
	    "long": "vitamin B6",
	    "alt": "Pyroxidine",
	    "unit": "mg",
	    "RDA":  ["men 14-50", 1.3,
		     "men 51+", 1.7,
		     "women 14-18", 1.2,
		     "women 19-50", 1.3,
		     "women 51+", 1.5,
		     "pregnant", 1.9,
		     "lactating", 2.0
		    ],
	    "UL": ["adults", 100]
	},
	{
	    "name": "vB9",
	    "long": "vitamin B9",
	    "alt": "Folate",
	    "unit": "μg",
	    "RDA":  ["adults 19+", 400,
		     "regular alcohol drinkers", 600,
		     "pregnant and lactating", 600
		    ],
	    "UL": [1000]
	},
	{
	    "name": "vB12",
	    "long": "vitamin B12",
	    "alt": "Cobalamin",
	    "unit": "μg",
	    "RDA":  ["adults 14+", 2.4,
		     "pregnant", 2.6,
		     "lactating", 2.8
		    ],
	    "UL": [],
	    "warning": [
		25, "above may have increased risk of bone fracture"]
	},
	{
	    "name": "vC",
	    "long": "Vitamin C",
	    "alt" : "ascorbic acid",
	    "RDA":  ["mg",
		     "men 19+", 90,
		     "women 19+", 75,
		     "pregnant", 18,
		     "lactating", 17,
		     "smokers", "+35"
		    ],
	    "UL": ["adults", 2000]
	},
	{
	    "name": "vD",
	    "long": "Vitamin D",
	    "long": "Vitamin D",
	    "RDA":  ["mg",
		     "men 19+", 90,
		     "women 19+", 75,
		     "pregnant", 18,
		     "lactating", 17,
		     "smokers", "+35"
		    ],
	    "UL": ["adults", 4000]
	},
	{
	    "name": "vE",
	    "long": "Vitamin E",
	    "alt":  "",
	    "RDA":  [D,
		    ],
	    "UL": []
	},
	{
	    "name": "vK",
	    "long": "Vitamin K",
	    "unit": "μg",
	    "RDA":  ["men 19+",120,
		     "women 19+", 90
		    ],
	    "UL": []
	},
	{
	    "name": "mCa",
	    "long": "Calcium",
	    "unit": "mg",
	    "RDA":  ["men 19-70", 1000,
		     "men 71+", 1200,
		     "women 19-50", 1000,
		     "women 51+", 1200
		    ],
	    "UL": ["adults", 2000]
	},
	{
	    "name": "mCr",
	    "long": "Chromium",
	    "unit": "μg",
	    "AI":  ["men 19-50", 35,
		    "women 19-50", 25,
		    "pregnant", 40,
		    "lactating", 45
		   ],
	    "UL": []
	},
	{
	    "name": "mCu",
	    "long": "Copper",
	    "unit": "μg",
	    "RDA":  ["adults 19+", 900,
		     "pregnant", 1000,
		     "lactating", 1300
		    ],
	    "UL": []
	},
	{
	    "name": "mFe",
	    "long": "Iron",
	    "unit": "mg",
	    "RDA":  ["men 19-50", 8,
		     "women 19-50", 18, 
		     "pregnant", 27,
		     "lactating", 9,
		     "boys 14-18", 11,
		     "girls 14-18", 15,
		     "lactating girls 14-18", 10,
		     "post-menopause women", 8
		    ],
	    "UL": []
	},
	{
	    "name": "mI",
	    "long": "Iodine",
	    "unit": "μg",
	    "RDA":  ["adults 19+", 95,
		     "children 1-8", 90,
		     "children 9-13", 120,
		     "children 14-18", 150,
		     "pregnant women", 220,
		    ],
	    "UL": []
	},    
	{
	    "name": "mMg",
	    "long": "Magnesium",
	    "unit": "μg",
	    "RDA":  ["men 19+", 420,
		     "women 19+", 320,
		     "pregnant", 360,
		     "lactating", 320
		    ],
	    "UL": ["adults", 350],
	    "ULsymp": "diarrhea, nausea, cramping"
	},
	{
	    "name": "mK",
	    "long": "Potassium",
	    "RDA":  [
	    ],
	    "UL": []
	},
	{
	    "name": "mMn",
	    "long": "Manganese",
	    "unit": "mg",
	    "AI":  ["men 19+", 2.3,
		    "women 19+", 1.8,
		    "pregnant", 2.0,
		    "lactating", 2.6
		   ],
	    "UL": []
	},
	{
	    "name": "mMo",
	    "long": "Molybdenum",
	    "unit": "μg",
	    "RDA":  [],
	    "UL": []
	},
	{
	    "name": "mP",
	    "long": "Phosphorus",
	    "unit": "mg",
	    "RDA":  ["adults 19+", 700
		    ],
	    "UL": ["adults 19-70", 4000,
		   "adults 71+", 3000,
		   "pregnant", 3500,
		   "lactating", 4000]
	},
	{
	    "name": "mSe",
	    "long": "Selenium",
	    "unit": "μg",
	    "RDA":  ["adults 19+", 55,
		     "pregnant", 60,
		     "lactating", 70
		    ],
	    "UL": ["adults", 400]
	    "defsymp": "Keshan disease, Kashin-Beck disease, nausea, vomiting, headaches, altered mental state/confusion, lethargy, seizures, coma",
	    "ULsymp": "metallic taste/bad breath, nausea/diarrhea, hair loss, nail brittleness or discoloration, skin rash or lesions, skin flushing, fatigue, irritability, muscle tenderness"	
	},
	{
	    "name": "mNa",
	    "long": "Sodium",
	    "unit": "mg",
	    "AI":  ["14+", 1500],
	    "CDRR": ["14+", 2300]
	},
	{
	    "name": "mZn",
	    "long": "Zinc",
	    "unit": "mg",
	    "RDA":  ["men 19+", 11,
		     "women 19+", 8
		    ],
	    "UL": []
	},
	{
	    "name": "caffeine",
	    "unit": "mg",
	    "class": "nutrient",
	    "UL": ["adults", 400],
	    "ULsymp": "",
	    "benefits":"",
	    "sources":[
		]
	},
	{
	    "name": "phytosterols",
	    "unit": "mg",
	    "class": "nutrient",
	    "UL": ["adults", 400],
	    "ULsymp": "",
	    "benefits":"",
	    "sources":["my.clevelandclinic.org/health/articles/17368-phytosterols-sterols--stanols",
		       "www.hopkinsmedicine.org/health/conditions-and-diseases/high-cholesterol/cholesterol-in-the-blood"
		]
	},
	{
	    "name": "oxalate",
	    "unit": "mg",
	    "class": "antinutrient",
	    "UL": [],
	    "ULsymp": "",
	    "benefits":"",
	    "sources":[]
	}
    ]
};
