# Assignment: Quick Reference for SQL (and specifically MySQL)

In this assignment, you will attempt to create a reference guide for
studying for the midterm. This homework is worth 100 points, but up to
100 bonus points for doing a spectacular job. Criteria:

1. Completeness: The more features you document, the better
2. Clarity: your summary should explain each feature.
3. Compactness: This is a quick reference, not a book. It is better to have more material than less, but given two equivalent guides, the shorter one is better.
4. 
5. Examples: Not only a concise explanation of each topic, but examples showing the different possibilities. 

## Topics

1. ACID properties
2. security commands (GRANT PRIVILEGES, ALTER, DELETE, ..)
   * examples of common operations
      * viewing all users
      * viewing a single user
      * giving a user permission to use the machine from an ip address
      * removing a user
      * etc.
3. DDL: at a minimum
    * create
    * drop
    * alter
    * truncate
    * primary key
    * foreign key
    * auto_increment
    * views on tables
4. Metadata
   * creating and selecting databases
   * schemas
   * viewing tables, views, stored procedures, etc
5. Normalization: very small, clear examples are essential!
   * Show an unnormalized table and the problems
     * [normalization](https://en.wikipedia.org/wiki/Database_normalization)
     * insertion consistency
     * delete consisency
   * Show tables in 1NF, 2NF, 3NF and show how each level solves a problem
5. Manipulation of data
   * SELECT, INSERT, UPDATE, DELETE
   * aggregate operations (sum, avg, max, min, etc)
   * sorting
   * subqueries
     * bonus for demonstrating how subqueries can be very very slow with simple examples
     
6. Optimization
   * The cost of operations O(n), O(log n), O(1)
   * Using explain to view to plan
   * latency
   * views
   * cursors
7. 

      6. example: small table
         1. get one row
	 2. insert one row
	 3. insert one row with only some values, leaving others default
	 4. update one row
	 5. delete one row
	 6. delete all rows
	 7. delete all rows where value is between a and b
	 8. inner join
	 9. left join
	 10. aggregate operations (count, max, sum)
	 11. cursor
      7. What did I forget? Go beyond
