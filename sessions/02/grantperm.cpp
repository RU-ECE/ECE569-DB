#include <iostream>
#include <fstream>
#include <string>
using namespace std;

int main() {
  ifstream f("netids.txt");
  string netid;
  while (getline(f, netid)) {
    cout << "GRANT ALL PRIVILEGES ON ECE569.* TO " <<
      netid << "@localhost;\n";
  }
}
    
