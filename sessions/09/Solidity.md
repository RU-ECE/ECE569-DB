# Solidity Notes

* Solidity is based on a number of languages, most like C++
* Streamlined syntax like Java
* Safe operations, designed to be executed on remote machines

## Resources
* [Installing compiler](https://docs.soliditylang.org/en/latest/installing-solidity.html)
* [Style Guide](https://docs.soliditylang.org/en/latest/style-guide.html)
* [Quick Reference](https://docs.soliditylang.org/en/latest/cheatsheet.html) 

1. Gas cost depends on computational cost of code
   1. CPU
   2. Memory use
   3. Gas rate: Can pay a premium to go first
   4. Effectively, you are renting a piece of other's computers
2. const and immutable are considered "cheaper" than regular variables
3. immutable variables can only be set once, in the constructor
4. constants are set at declaration time

## Examples

### License and pragma

* Since code goes out to other parties, convention is to create a license file
* pragma solidity defines version numbers (can be min and max)

```solidity
// SPDX-License-Identifier: GPL-3.0
pragma solidity >=0.6.0 <0.9.0;

abstract contract A { // an abstract class is a parent for others
    function f() public virtual pure;
}

contract B is A { // A contract can inherit from a parent
    function f() public pure override { // and must override the method
        // ...
    }
}
```

### Immutable and Constant

* Constants are symbolic values that can't change
* Immutable variables are variables that are set once in the constructor, then can't change
* More gas is charged for variables that can change
* The meter is always running!

```solidity
pragma solidity ^0.8.24;

contract Immutable {
  address public constant MY_WALLET =
        0x777788889999AaAAbBbbCcccddDdeeeEfFFfCcCC;
  address public constant MY_WALLET2 =
        0x777788889999AaAAbBbbCcccddDdeeeEfFFfDDDD;
  address public immutable MY_ADDRESS; // convention: uppercase constant variables
  uint256 public immutable MY_UINT;

  constructor(uint256 _myUint) {
    MY_ADDRESS = msg.sender;
    MY_UINT = _myUint;
  }
}
```

## Storage Types

* State variables
  * Stored on blockchain
  * Can be changed
* Memory
  * variables in functions are in RAM and disappear

### State Variables
```solidity
pragma solidity ^0.8.24;

contract SimpleStorage {
    uint256 public num;    // State variable to store a number

    // You need to send a transaction to write to a state variable.
    function set(uint256 _num) public {
        num = _num;
    }

    // You can read from a state variable without sending a transaction.
    function get() public view returns (uint256) {
        return num;
    }
}
```


### Money Units

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract EtherUnits {
    uint256 public oneWei = 1 wei;
    // 1 wei is equal to 1
    bool public isOneWei = 1 wei == 1;

    uint256 public oneEther = 1 ether; // 10^18 wei
    uint256 public oneGwei = 1 gwei;   // 10^9 wei
    bool public isOneEther = 1 ether == 1e18;
}
```
### Time Units

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;
// seconds, minutes, hours, days...
  bool public isOneSecond = 1 seconds == 1;
  bool public isOneWeek = 1 days == 86400;
```

### Enumerated Values

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract Enum {
    enum Status {
        Pending,
        Shipped,
        Accepted,
        Rejected,
        Canceled
    }

    // Default value is the first element listed in
    // definition of the type, in this case "Pending"
    Status public status;

    // Returns uint
    // Pending  - 0
    // Shipped  - 1
    // Accepted - 2
    // Rejected - 3
    // Canceled - 4
    function get() public view returns (Status) {
        return status;
    }

    // Update status by passing uint into input
    function set(Status _status) public {
        status = _status;
    }

    // You can update to a specific enum like this
    function cancel() public {
        status = Status.Canceled;
    }

    // delete resets the enum to its first value, 0
    function reset() public {
        delete status;
    }
}

### Arrays

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract Array {
    // Several ways to initialize an array
    uint256[] public arr = new uint256[](3000);
    uint256[] public arr2 = [1, 2, 3];
    // Fixed sized array, all elements initialize to 0
    uint256[10] public myFixedSizeArr;

    function get(uint256 i) public view returns (uint256) {
        return arr[i];
    }

    // Solidity can return the entire array.
    // But this function should be avoided for
    // arrays that can grow indefinitely in length.
    function getArr() public view returns (uint256[] memory) {
        return arr;
    }

    function push(uint256 i) public {
        // Append to array
        // This will increase the array length by 1.
        arr.push(i);
    }

    function pop() public {
        // Remove last element from array
        // This will decrease the array length by 1
        arr.pop();
    }

    function getLength() public view returns (uint256) {
        return arr.length;
    }

    function remove(uint256 index) public {
        // Delete does not change the array length.
        // It resets the value at index to it's default value,
        // in this case 0
        delete arr[index];
    }

    function examples() external {
        // create array in memory, only fixed size can be created
        uint256[] memory a = new uint256[](5);

    }
}
```

### Structures

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;
// This is saved 'Parties.sol'

struct Parties {
    address from;
    address to;
    address agent;
}
```

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

import "./Parties.sol";

contract ContractWith3Parties {
  Parties public parties;
}
```
### Control Flow

* Solidity supports for, while, and do while loops.
* Remember that all computation costs gas. Be careful you can spend a lot of money
* If you hit the gas limit, the transaction will fail.
* Remember, this language is designed for short transactions borrowing other people's computers

```solidity
pragma solidity ^0.8.24;

contract Loop {
    function loops() public {
        for (uint i = 0; i < 10; i++) {
        }

        for (uint i = 0; i < 10; i++) {
            if (i == 3) {
                continue;    // Skip to next iteration
            }
            if (i == 5) {
                break;       // Exit loop early
            }
        }

        uint256 j = 0;
        while (j < 10) {
            j++;
        }

        do {
          j++;
        } while (j < 20)
    }
}
```

* if statements
```solidity

```

### Mapping

```solidity
pragma solidity ^0.8.24;

contract Mapping {
    // Mapping from address to uint
    mapping(address => uint256) public myMap;

    function get(address _addr) public view returns (uint256) {
        return myMap[_addr]; // if value unset, returns default
    }

    function set(address _addr, uint256 _i) public {
        myMap[_addr] = _i;         // Update the value at this address
    }

    function remove(address _addr) public {
        // Reset the value to the default value.
        delete myMap[_addr];
    }
}

contract NestedMapping {
    // Nested mapping (mapping from address to another mapping)
    mapping(address => mapping(uint256 => bool)) public nested;

    function get(address _addr1, uint256 _i) public view returns (bool) {
        // You can get values from a nested mapping
        // even when it is not initialized
        return nested[_addr1][_i];
    }

    function set(address _addr1, uint256 _i, bool _boo) public {
        nested[_addr1][_i] = _boo;
    }


    function remove(address _addr1, uint256 _i) public {
        delete nested[_addr1][_i];
    }
}
```

### Constructor

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

// Base contract X
contract X {
    string public name;

    constructor(string memory _name) {
        name = _name;
    }
}
```

### Visibility

* public - any contract and account can call
* private - only inside the contract that defines the function
* internal- only inside contract that inherits an internal function
* external - only other contracts and accounts can call

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.2;
contract Base {
    // Private is visible only within this contract
    function privateFunc() private pure returns (string memory) {
        return "private function called";
    }

    function testPrivateFunc() public pure returns (string memory) {
        return privateFunc();
    }

    // Internal function can be called from this contract or from children
    function internalFunc() internal pure returns (string memory) {
        return "internal function called";
    }

    function testInternalFunc() public pure virtual returns (string memory) {
        return internalFunc();
    }

    // Public functions can be called from anywhere
    function publicFunc() public pure returns (string memory) {
        return "public function called";
    }

    // External functions can only be called from outside this object
    // more gas-efficient than public
    function externalFunc() external pure returns (string memory) {
        return "external function called";
    }

    // Example: illegal because we cannot call the external function
    // function testExternalFunc() public pure returns (string memory) {
    //     return externalFunc();
    // }

    // State variables
    string private privateVar = "my private variable";
    string internal internalVar = "my internal variable";
    string public publicVar = "my public variable";
    // State variables cannot be external
    // string external externalVar = "my external variable";
}

contract Child is Base {
    // Inherited contracts do not have access to private functions
    // and state variables.
    // function testPrivateFunc() public pure returns (string memory) {
    //     return privateFunc();
    // }

    // Internal function can be called inside child contracts.
    function testInternalFunc() public pure override returns (string memory) {
        return internalFunc();
    }
}
```

## Inheritance

* Solidity uses a modified c++ and Java-like model of inheritance
  * contract = Class
    * data
    * functions
    * constructor: special function that initializes the object
  * abstract contract = abstract class
    * Only intended for another contract to inherit
    * Shared between multiple classes
    * May have data, but does not exist by itself
  * interface
    * Only a specification of methods to be implemented by children 
* Contract can inherit from multiple contracts
* Rightmost parent overrides previous ones

### Simplest inheritance
```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.2;
contract A {
    function foo() public pure virtual returns (string memory) {
        return "A";
    }
}

// Contracts inherit other contracts by using the keyword 'is'.
contract B is A {
    // Override A.foo()
    function foo() public pure virtual override returns (string memory) {
        return "B";
    }
}
```


### Multiple inheritance
```solidity
contract A {
    function foo() public pure virtual returns (string memory) {
        return "A";
    }
}

// Contracts inherit other contracts by using the keyword 'is'.
contract B is A {
    // Override A.foo()
    function foo() public pure virtual override returns (string memory) {
        return "B";
    }
}

contract C is A {
    // Override A.foo()
    function foo() public pure virtual override returns (string memory) {
        return "C";
    }
}
// Contracts can inherit from multiple parent contracts.
// When a function is called that is defined multiple times in
// different contracts, parent contracts are searched from
// right to left, and in depth-first manner.

contract D is B, C {
    // D.foo() returns "C"
    // since C is the right most parent contract with function foo()
    function foo() public pure override(B, C) returns (string memory) {
        return super.foo();
    }
}

contract E is C, B {
    // E.foo() returns "B"
    // since B is the right most parent contract with function foo()
    function foo() public pure override(C, B) returns (string memory) {
        return super.foo();
    }
}
```
### Interfaces
```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract Counter {
    uint256 public count;

    function increment() external {
        count += 1;
    }
}

interface ICounter {
    function count() external view returns (uint256);

    function increment() external;
}

contract MyContract {
    function incrementCounter(address _counter) external {
        ICounter(_counter).increment();
    }

    function getCount(address _counter) external view returns (uint256) {
        return ICounter(_counter).count();
    }
}
```

## Payable

```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract Payable {
    // Payable address can send Ether via transfer or send
    address payable public owner;

    // Payable constructor can receive Ether
    constructor() payable {
        owner = payable(msg.sender);
    }

    // Function to deposit Ether into this contract.
    // Call this function along with some Ether.
    // The balance of this contract will be automatically updated.
    function deposit() public payable {}

    // Call this function along with some Ether.
    // The function will throw an error since this function is not payable.
    function notPayable() public {}

    // Function to withdraw all Ether from this contract.
    function withdraw() public {
        // get the amount of Ether stored in this contract
        uint256 amount = address(this).balance;

        // send all Ether to owner
        (bool success,) = owner.call{value: amount}("");
        require(success, "Failed to send Ether");
    }

    // Function to transfer Ether from this contract to address from input
    function transfer(address payable _to, uint256 _amount) public {
        // Note that "to" is declared as payable
        (bool success,) = _to.call{value: _amount}("");
        require(success, "Failed to send Ether");
    }
}
```

## Sending/Receiving Ether

To send ETH, contract must call one of
* transfer (2300 gas, throws error)
* send (2300 gas, returns bool)
* call (forward all gas or set gas, returns bool)
To receive ETH, contract must implement one of
* receive() external payable
* fallback() external payable

Because these functions are declared external
* a contract cannot pay itself
* An external agent must pay 

Safety
* Guard against re-entrancy
  * Call in conjunction with reentrancy modifier
  * Making all state changes before calling other contracts
  * Using re-entrancy guard modifier


```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract ReceiveEther {
    /*
    Which function is called, fallback() or receive()?

           send Ether
               |
         msg.data is empty?
              / \
            yes  no
            /     \
    receive() exists?  fallback()
         /   \
        yes   no
        /      \
    receive()   fallback()
    */

    // Function to receive Ether. msg.data must be empty
    receive() external payable {}

    // Fallback function is called when msg.data is not empty
    fallback() external payable {}

    function getBalance() public view returns (uint256) {
        return address(this).balance;
    }
}

contract SendEther {
    function sendViaTransfer(address payable _to) public payable {
        // This function is no longer recommended for sending Ether.
        _to.transfer(msg.value);
    }

    function sendViaSend(address payable _to) public payable {
        // Send returns a boolean value indicating success or failure.
        // This function is not recommended for sending Ether.
        bool sent = _to.send(msg.value);
        require(sent, "Failed to send Ether");
    }

    function sendViaCall(address payable _to) public payable {
        // Call returns a boolean value indicating success or failure.
        // This is the current recommended method to use.
        (bool sent, bytes memory data) = _to.call{value: msg.value}("");
        require(sent, "Failed to send Ether");
    }
}
```

## Gas and Gas Price

* Gas is a unit of computation
* Gas spent: total amount of gas used in a transaction
* Gas price: price per gas unit, set by user
  * Priority handling given to parties paying more (like Disneyland)
* Gas limit: maximum amount of gas to be spent, set by user
* Block gas limit: maximum spent in a block, defined by the network
 
```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.24;

contract Gas {
    uint256 public i = 0;

    // Using up all of the gas that you send causes your transaction to fail.
    // State changes are undone.
    // Gas spent are not refunded.
    function forever() public {
        // Here we run a loop until all of the gas are spent
        // and the transaction fails
        while (true) {
            i += 1;
        }
    }
}
```

## Import

* Import an entire solidity file
* Import specific entities from within a file
* Import specific entities and give them aliases

```solidity

## Escrow

* An escrow is a contractual agreement
  * A third party receives and holds money from one party
  * Pays it to another party after a certain condition has been met.
  * In DeFi, this can be a smart contract not a person
    * Actual third parties are needed if there is a real-world condition to be evaluated
* Example: a simple escrow protocol that holds funds until a specified duration has passed. This could be used to give a friend some Ethereum for their birthday, or to save money for a specific occasion.
  * Escrow
    * Provide the end-user interface for escrowing and redeeming funds.
    * Stores all of the escrowed funds.
  * EscrowNFT
    * Store the details of individual escrows as NFT
    * Allow users to transfer immature escrows between one another

### Escrow NFT
```solidity
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.2;

import "@openzeppelin/contracts/access/Ownable.sol";
import "@openzeppelin/contracts/token/ERC721/extensions/ERC721Burnable.sol";
import "@openzeppelin/contracts/token/ERC721/extensions/ERC721Enumerable.sol";

contract EscrowNFT is ERC721Burnable, ERC721Enumerable, Ownable {
    uint256 public tokenCounter = 0;

    // NFT data
    mapping(uint256 => uint256) public amount;
    mapping(uint256 => uint256) public matureTime;

    constructor() ERC721("EscrowNFT", "ESCRW") {
    }
}
```

```solidity

```

```solidity

```

```solidity

```

```solidity

```