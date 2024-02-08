#include <cstdlib>
#include <iostream>

using namespace std;
/*
  Include directly the different
  headers from cppconn/ and mysql_driver.h + mysql_util.h
  (and mysql_connection.h). This will reduce your build time!
*/
#include "mysql_connection.h"

#include <cppconn/driver.h>
#include <cppconn/exception.h>
#include <cppconn/resultset.h>
#include <cppconn/statement.h>
#include <cppconn/prepared_statement.h>
#include <chrono>
using namespace sql;

void benchmark_statement(Connection* con, int n, const char query[]) {
    auto t0 = std::chrono::high_resolution_clock::now();
    Statement* stmt = con->createStatement();
    for (int i = 0; i < n; i++) {
        stmt->execute(query);
    }
    delete stmt;
    auto t1 = std::chrono::high_resolution_clock::now();
    std::chrono::duration<double> elapsed = std::chrono::duration_cast<std::chrono::duration<double> >(t1 - t0);
    cout << "Elapsed time: " << elapsed.count()/n << ": " << query << endl;
}

/*
	Note: The point of PreparedStatements is to create the object once, 
	not every time you do a query. To keep all the code in one place
	in this benchmarking function it is done outside the timer, but
	in a real situation it would be done ONCE for your code.
	The benefit is not only performance, it also 
*/
void benchmark_prepared(Connection* con, int n) {
	sql::PreparedStatement* prep_stmt =
		con->prepareStatement("INSERT INTO Accounts (owner, balance) VALUES (?, ?)");

    auto t0 = std::chrono::high_resolution_clock::now();

    for (int i = 0; i < n; i++) {
			prep_stmt->setInt(1, 1);
			prep_stmt->setDouble(2, 1.0);
			prep_stmt->execute();
    }
    auto t1 = std::chrono::high_resolution_clock::now();
		delete prep_stmt;
    std::chrono::duration<double> elapsed =
			std::chrono::duration_cast<std::chrono::duration<double> >(t1 - t0);
    cout << "Elapsed time: " << elapsed.count()/n << ": prepared statement" << endl;
}

void simple_test(Connection* con) {
    con->setSchema("acc"); // get into the acc database for testing
    Statement* stmt = con->createStatement(); // create a statement. Prepared statements are better
    if (stmt == nullptr) throw "Could not create statement.";
    ResultSet* res = stmt->executeQuery("SELECT 'Hello World!' AS _message");
    if (res == nullptr) throw "Could not execute query.";
    while (res->next()) {
        cout << "\t... MySQL replies: ";
        /* Access column data by alias or column name */
        cout << res->getString("_message") << endl;
        cout << "\t... MySQL says it again: ";
        /* Access column data by numeric offset, 1 is the first column */
        cout << res->getString(1) << endl;
    }
}

// a simple query against a table returning a result set
void test_query(Connection* con) {
    Statement* stmt = con->createStatement();
		// create a statement. Prepared statements are faster and more secure
		// if there are params. In this case there are none.
		ResultSet* res = stmt->executeQuery("SELECT * FROM Accounts");
    if (res == nullptr) throw "Could not execute query.";
    while (res->next()) {
        cout << res->getInt("id") << "\t" 
            << res->getInt("owner") << "\t"
            << res->getDouble("balance") << endl;
    }
		delete stmt;
		delete res;
}

int main(int argc, char* argv[]) {
try {
	//Note: DO NOT EMBED YOUR PASSWORD IN YOUR CODE. THIS PART OF THE EXAMPLE IS STUPID!
	const char* url = argc > 1 ? argv[1] : "tcp://127.0.0.1:3306";
	const char* userid = argc > 2 ? argv[2] : "dov";
	const char* passwd = argc > 3 ? argv[3] : "test";
	Driver* driver = get_driver_instance();
	if (driver == nullptr) throw "Could not get driver";

	// connect to hardcoded local mysql server
	// note: not good practice to embed password in code! just for first demo
	Connection* con = driver->connect(url, userid, passwd);
	simple_test(con);
	test_query(con);
	const int n = 10'000;
	benchmark_statement(con, n, "INSERT INTO Accounts (owner, balance) VALUES (1, 1000005.0)");
	benchmark_statement(con, n, "CALL transfer(5000, 1, 1.0)"); // transfer $1M $1 at a time
	benchmark_prepared(con, n);

	/*
		clean up. This is not a good way to do it, because if you forget you leak the objects
		TODO: use smart pointer to automatically deallocate.
		This API does not seem to work with std::unique_ptr
	*/
	delete con;
} catch (sql::SQLException &e) {
  cout << "# ERR: SQLException in " << __FILE__;
  cout << "(" << __FUNCTION__ << ") on line " << __LINE__ << endl;
  cout << "# ERR: " << e.what();
  cout << " (MySQL error code: " << e.getErrorCode();
  cout << ", SQLState: " << e.getSQLState() << " )" << endl;
} catch (const char* msg) {
    cerr << msg << endl;
}

cout << endl;

return 0;
}
