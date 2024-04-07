function benchmark(func, n) {
    let t0 = Date.now(); // get time before
    func(n);
    let t1 = Date.now(); // get time after
    print(func.name + ": " + (t1 - t0));
}

function insertTest(n) {
    for (let i = 0; i < n; i++) {
        db.bench.insertOne({"i": i});
    } 
}

function insertTestBatch(n) {
    let a = [];
    for (let i = 0; i < n; i++) {
        a[i] = {"i": i};
    }
    db.bench.insertMany(a);
}

db.createCollection("bench");
const n = 100000;
//benchmark(insertTest, n);
db.bench.deleteMany({}); // clear the entire bench collection

benchmark(insertTestBatch, n);  