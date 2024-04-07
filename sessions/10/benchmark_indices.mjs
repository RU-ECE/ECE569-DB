/*
* Author: Dov Kruger
* Date: 2024-04-04
* License: CC0
* Functions to benchmark JavaScript code, both synchronous and asynchronous versions
*/
function benchmark(func, n) {
    let t0 = Date.now(); // get time before
    func(n);
    let t1 = Date.now(); // get time after
    print(func.name + "\t" + n + "\t" + (t1 - t0));
}

function buildRandomPairs(n) {
    let pairs = [];
    for (var i = 0; i < n; i++) {
        // generate a random string 10 letters long
        let name = Math.random().toString(36).substring(2, 12);
        pairs.push({"name": name, val: i});
    }
    return pairs;
}

function insertTest(n) {
    db.createCollection("pairs");
    db.pairs.insertMany(buildRandomPairs(n));
    db.pairs.drop();
}

function insertTest2(n) {
    db.createCollection("pairs");
    db.pairs.createIndex({val: 1}, {unique: true});
    db.pairs.insertMany(buildRandomPairs(n));
    db.pairs.drop();
}

for (let n = 1000; n <= 100000; n *= 10) {
    benchmark(insertTest, n);
}

for (let n = 1000; n <= 100000; n *= 10) {
    benchmark(insertTest2, n);
}

const n = 100000;
db.pairs.insertMany(buildRandomPairs(n));
print(db.pairs.countDocuments())
db.pairs.countDocuments({val: {$lte: 1000, $gt: 900}})
db.pairs.find({ val: { $gte: 500, $lt: 505} })

