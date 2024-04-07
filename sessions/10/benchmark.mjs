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

module.exports = benchmark;