#include <fstream>
#include <nlohmann/json.hpp>
#include <iostream>
using json = nlohmann::json;

using namespace std;
int main() {
    ifstream f("example.json");
    json data = json::parse(f);

    for (int i  = 0; i < data.size(); i++) {
        cout << data[i]["firstname"] << '\t';
        cout << data[i]["lastname"] << '\t';
        cout << data[i]["hobby"] << endl;
    }
}