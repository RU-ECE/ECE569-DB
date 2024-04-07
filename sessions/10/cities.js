/*
  Cities.js

*/

db.createCollection("cities");
db.cities.insertMany([
{ country: "CHN", name: "Shanghai",       pop: 24256800},
{ country: "CHN", name: "Beijing",        pop: 21516000},
{ country: "IND", name: "Delhi",          pop: 16753235},
{ country: "PAK", name: "Karachi",        pop: 15741406},
{ country: "CHN", name: "Guangzhou",      pop: 13080500},
{ country: "CHN", name: "Shenzhen",       pop: 13000000},
{ country: "TUR", name: "Istanbul",       pop: 15029231},
{ country: "JPN", name: "Tokyo",          pop: 37833000},
{ country: "RUS", name: "Moscow",         pop: 12506468},
{ country: "BRA", name: "São Paulo",      pop: 21292893},
{ country: "CHN", name: "Tianjin",        pop: 11154500},
{ country: "EGY", name: "Cairo",          pop: 16000000},
{ country: "NGA", name: "Lagos",          pop: 16060303},
{ country: "BGD", name: "Dhaka",          pop: 12797394},
{ country: "BRA", name: "Rio de Janeiro", pop: 15993583},
{ country: "IDN", name: "Jakarta",        pop: 25857257},
{ country: "USA", name: "New York City",  pop: 19153634},
{ country: "CHN", name: "Chongqing",      pop: 11879300},
{ country: "IND", name: "Mumbai",         pop: 23355000},
{ country: "PAK", name: "Lahore",         pop: 11830500}     
]);

db.cities.find({})
db.cities.find({}).sort({name: 1})
db.cities.find({}).sort({name: -1}).limit(5)
db.cities.find({country: "CHN"}).sort({name: 1})
