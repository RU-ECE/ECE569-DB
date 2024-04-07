db.bulktest.drop();
db.bulktest.bulkWrite([
  {insertOne: {a: 1, b:2}},
  {insertOne: {a: 5, b:3}},
  {insertOne: {a: 13, b:12}},
  {insertOne: {a: [1, 2, 3], b: [4, 5, 6]}},
  {insertOne: {a: [99, 5, 98, 11], b: [1]}}
])

db.bulktest.bulkWrite([
    { updateOne: { filter: { a: 5 }, // Filter to match the document to update
                   update: { $set: { b: 10 } }
                 }
    }, // Update operation, setting the value of 'b' to 10
      
    // Delete operation
    { deleteOne: {filter: { a: 1 }}}, // Filter to match the document to delete
    { deleteOne: {filter: { a: 13 }}} // Filter to match the document to delete
  ]);

print(db.bulktest.find())
