#include <cstdlib>
#include <iostream>
#include <vector>

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

class Account {
    public: // not great Object-oriented style...
        int id;
        int owner;
        double balance;
    public:
        Account(int id, int owner, double balance)
        : id(id), owner(owner), balance(balance) {}
};
void test(Driver* driver, const char* userid, const char* passwd) {
    sql::Connection* conn = driver->connect("tcp://127.0.0.1:3306", userid, passwd);
    conn->setSchema("acc"); // get into the acc database for testing
    sql::Statement* stmt = conn->createStatement();
    stmt->execute("INSERT INTO Accounts(owner, balance) VALUES(1, 0.0)");
     // insert a recordSELECT count(*) FROM Accounts");   
    ResultSet* res = stmt->executeQuery("SELECT * FROM Accounts");
    vector<Account> accounts;
    while (res->next()) { // get the next row
        int id = res->getInt("id"); // good defensive coding, ask for column by name
        //string name = res->getString("owner"); // get a string parameter
        int owner = res->getInt("owner"); // but I Used an int 
        double balance = res->getDouble("balance"); 
            // Dov says NEVER USE FLOAT FOR BUSINESS!!!! STUPID ME
        accounts.push_back(Account(id, owner, balance));
    }    
    for (auto a : accounts) {
        cout << a.id << " " << a.owner << " " << a.balance << endl;
    }
//in JAVA, DO THIS!    stmt.close();
//    delete conn;
}

int main(int argc, char** argv) {
    const char* userid = argc > 1 ? argv[1] : "dov";
    const char* passwd = argc > 2 ? argv[2] : "test";
    Driver* driver = get_driver_instance();
    for (int i = 0; i < 10; i++) {
        test(driver, userid, passwd);
    }
}