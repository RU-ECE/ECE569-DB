# Midterm Review Guide

1. DDL
```sql
CREATE TABLE integers (
    a tinyint, // 8-bit -128 .. 127
    b smallint unsigned, //16-nbit 0..65535
    c int,             // 32-bit -2.1 billion ... 2.1 billion
    d bigint          /64-bit
);
INSERT INTO integers VALUES(-127, 105, 3, -123456789012345);
INSERT INTO integers VALUES(-128, 0, 12345678, 123456789012345);
INSERT INTO integers VALUES(-127, null, null, null);
INSERT INTO integers(c) VALUES(912);
```
   * the order of columns is by default the order in which they are specified in the table
   * if no primary key is specified, is one selected automatically? NO
   * What row order will this table be in? the order of insertion
   * What is the complexity
   * Complexity: O(n)  O(log n),   O(1)
```sql
    SELECT * FROM integers;
    SELECT d,c,b,a FROM integers;
    SELECT min(a) FROM integers; // O(n)

```


1. Recreate the table to be more efficient for searching on a

```sql
CREATE TABLE integers (
    a tinyint, // 8-bit -128 .. 127
    b smallint unsigned, //16-nbit 0..65535
    c int,             // 32-bit -2.1 billion ... 2.1 billion
    d bigint          /64-bit
);
INSERT INTO integers VALUES(1, 5, null, null);
INSERT INTO integers VALUES(3, 5, null, null);
INSERT INTO integers VALUES(3, 8, null, null);
INSERT INTO integers VALUES(7, 8, null, null);

/*
    Views are only for controlling access to tables
    allow granting permissions to a subset of data
*/
CREATE VIEW intview AS
  SELECT a,b,c FROM integers;

/*
    Indices are for optimizing queries
    Tradeoff: each index makes insertion slower
*/
CREATE INDEX sorted_integers ON integers(a);

SELECT min(a) FROM integers; // O(log n)
SELECT min(a), max(a), avg(a) FROM integers; // O(n)
//O(log64(n)) 64 = 2^6      1 billion = 2^30


SELECT avg(a) FROM integers GROUP BY b; // O(n log n) to sort on B
1 5
3 5
4/2 = 2

3 8
7 8

CREATE INDEX sorted_b_integers ON integers(b);
SELECT avg(a) FROM integers GROUP BY b; //O(n) n is the total number of rows in the table

SELECT avg(d) FROM integers;

```

3. STORED PROCEDURES
  * How do stored procedures make databases faster?
    * reduce latency
      * precompiles the code (no delay for interpretation)
      * pre-optimize
      * executing on the server
        * Communication between stored procedure and server is fast, low-latency
```sql
    SELECT max(pop) FROM Cities; // O(n) or O(log n) IF index on pop
    SELECT * FROM Cities where pop > 10000000 ORDER BY pop limit = 1; // all big cities

    SELECT max(pop), min(pop) FROM Cities WHERE pop>10000000;



    SELECT pop FROM Cities; // O(n)


```
 Examples: 
   * Write a stored procedure to insert 1000 rows into table
   * Write a stored procedure to do a transaction
      * Given 2 accounts, transfer money from A to B if there is enough money in A
      * Write the transaction into a transaction log
  



