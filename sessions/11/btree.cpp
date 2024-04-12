#include <iostream>

using namespace std;

template <typename key, uint32_t degree>
class btree {
private:
    struct node {
        key keys[degree - 1];
        node *children[degree];
        size_t num_keys;
        bool is_leaf;

        node() : num_keys(0), is_leaf(true) {
            for (uint32_t i = 0; i < degree; ++i)
                children[i] = nullptr;
        }
    };

    node root;

    // Helper functions for insertion and deletion
    key get_predecessor(node* n, int index) {
        node* curr = n->children[index];
        while (!curr->is_leaf)
            curr = curr->children[curr->num_keys];

        return curr->keys[curr->num_keys - 1];
    }

    key get_successor(node* n, int index) {
        node* curr = n->children[index + 1];
        while (!curr->is_leaf)
            curr = curr->children[0];

        return curr->keys[0];
    }

    void insert_non_full(node *n, const key &k)
    {
        int i = n->num_keys - 1;
        if (n->is_leaf)
        {
            while (i >= 0 && k < n->keys[i])
            {
                n->keys[i + 1] = n->keys[i];
                --i;
            }
            n->keys[i + 1] = k;
            ++n->num_keys;
        }
        else
        {
            while (i >= 0 && k < n->keys[i])
            {
                --i;
            }
            ++i;
            if (n->children[i]->num_keys == 2 * degree - 1)
            {
                split_child(n, i, n->children[i]);
                if (k > n->keys[i])
                    ++i;
            }
            insert_non_full(n->children[i], k);
        }
    }

    void split_child(node *parent, int index, node *child)
    {
        // Implementation of split_child
    }

    // Add other helper functions for deletion here

public:
    btree() {}

    void insert(const key &k)
    {
        node *r = &root;
        if (r->num_keys == 2 * degree - 1)
        {
            node *s = new node();
            root = *s;
            s->is_leaf = false;
            s->children[0] = r;
            split_child(s, 0, r);
            insert_non_full(s, k);
        }
        else
        {
            insert_non_full(r, k);
        }
    }
    void remove(const key &k)
    {
        remove_from_node(&root, k);
    }

    void remove_from_node(node *n, const key &k)
    {
        int i = 0;
        while (i < n->num_keys && k > n->keys[i])
            ++i;

        if (i < n->num_keys && k == n->keys[i])
        {
            if (n->is_leaf)
            {
                // Case 1: Key exists in a leaf node
                remove_from_leaf(n, i);
            }
            else
            {
                // Case 2: Key exists in a non-leaf node
                remove_from_non_leaf(n, i);
            }
        }
        else
        {
            if (n->is_leaf)
            {
                // Case 3: Key does not exist in the tree
                std::cout << "Key " << k << " does not exist in the tree." << std::endl;
                return;
            }
            bool is_last_child = (i == n->num_keys);
            if (n->children[i]->num_keys < degree)
            {
                // Case 4a: Child has fewer than t keys, need to borrow or merge
                fill_child(n, i);
            }
            if (is_last_child && i > n->num_keys)
            {
                // If the child which is supposed to contain the key is last,
                // then recurse on the (i-1)-th child.
                remove_from_node(n->children[i - 1], k);
            }
            else
            {
                // Otherwise, recurse on the i-th child.
                remove_from_node(n->children[i], k);
            }
        }
    }

    void remove_from_leaf(node *n, int index)
    {
        for (int i = index + 1; i < n->num_keys; ++i)
            n->keys[i - 1] = n->keys[i];
        --n->num_keys;
    }

    void remove_from_non_leaf(node *n, int index)
    {
        key k = n->keys[index];
        if (n->children[index]->num_keys >= degree) {
            key pred = get_predecessor(n, index);
            n->keys[index] = pred;
            remove_from_node(n->children[index], pred);
        } else if (n->children[index + 1]->num_keys >= degree) {
            key succ = get_successor(n, index);
            n->keys[index] = succ;
            remove_from_node(n->children[index + 1], succ);
        } else {
            merge_children(n, index);
            remove_from_node(n->children[index], k);
        }
    }

    void fill_child(node *n, int index) {
        if (index != 0 && n->children[index - 1]->num_keys >= degree) {
            borrow_from_prev(n, index);    // Borrow from left sibling
        } else if (index != n->num_keys && n->children[index + 1]->num_keys >= degree) {
            borrow_from_next(n, index);    // Borrow from right sibling
        } else {                           // Merge with a sibling
            if (index != n->num_keys) {
                merge_children(n, index);
            } else {
                merge_children(n, index - 1);
            }
        }
    }

    void borrow_from_prev(node *n, int index) {
        node *child = n->children[index];
        node *sibling = n->children[index - 1];

        // Move all keys of child to the right
        for (int i = child->num_keys - 1; i >= 0; --i)
            child->keys[i + 1] = child->keys[i];

        // If child is not a leaf, move all its child pointers one step ahead
        if (!child->is_leaf) {
            for (int i = child->num_keys; i >= 0; --i)
                child->children[i + 1] = child->children[i];
        }

        // Setting child's first key equal to keys[index-1] from the current node
        child->keys[0] = n->keys[index - 1];

        // Moving sibling's last child as the first child of child
        if (!child->is_leaf)
            child->children[0] = sibling->children[sibling->num_keys];

        // Moving the key from the sibling to the parent
        n->keys[index - 1] = sibling->keys[sibling->num_keys - 1];

        // Incrementing and decrementing the key count of child and sibling
        ++child->num_keys;
        --sibling->num_keys;
    }

    void borrow_from_next(node* n, int index) {
        node* child = n->children[index];
        node* sibling = n->children[index + 1];

        // Setting child's (degree - 1)th key equal to keys[index] from the current node
        child->keys[(degree - 1)] = n->keys[index];

        // Moving sibling's first child as the last child of child
        if (!child->is_leaf)
            child->children[degree] = sibling->children[0];

        // Moving the key from the sibling to the parent
        n->keys[index] = sibling->keys[0];

        // Moving all keys in sibling one step behind
        for (int i = 1; i < sibling->num_keys; ++i)
            sibling->keys[i - 1] = sibling->keys[i];

        // Moving the child pointers one step behind
        if (!sibling->is_leaf) {
            for (int i = 1; i <= sibling->num_keys; ++i)
                sibling->children[i - 1] = sibling->children[i];
        }

        // Incrementing and decrementing the key count of child and sibling
        --sibling->num_keys;
        ++child->num_keys;
    }

    void merge_children(node* n, int index) {
        node* child = n->children[index];
        node* sibling = n->children[index + 1];

        // Pulling a key from the current node and inserting it into (degree - 1)th position of child
        child->keys[degree - 1] = n->keys[index];

        // Copying the keys from sibling to child
        for (int i = 0; i < sibling->num_keys; ++i)
            child->keys[i + degree] = sibling->keys[i];

        // If not a leaf, copy the child pointers from sibling to child
        if (!child->is_leaf) {
            for (int i = 0; i <= sibling->num_keys; ++i)
                child->children[i + degree] = sibling->children[i];
        }

        // Move all keys after index in the current node one step before
        for (int i = index + 1; i < n->num_keys; ++i)
            n->keys[i - 1] = n->keys[i];

        // Move the child pointers after (index+1) in the current node one step before
        for (int i = index + 2; i <= n->num_keys; ++i)
            n->children[i - 1] = n->children[i];

        // Update the key count of child and the current node
        child->num_keys += sibling->num_keys + 1;
        --n->num_keys;

        // Free the memory occupied by sibling
        delete sibling;
    }
    void inorder(const node* p) const {
        if (p) {
        int i;
        for (i = 0; i < p->num_keys; i++) {
            if (!p->is_leaf)
                inorder(p->children[i]);
            cout << p->keys[i] << " ";
        }
        if (!p->is_leaf)
            inorder(p->children[i]);
    }
    }
    friend ostream& operator <<(ostream& s, const btree& bt) {
        bt.inorder(&bt.root);
        return s;
    }
};

    int main() {
        btree<int, 4> bt1; // create a btree of integer values, degree 4

        // insertion test
//        int inserts[] = {10, 20, 5, 25, 40, 11, 3, 5, 19, 60, 22, 8, -1, 92, 14};
        int inserts[] = {10, 20, 5, 25, 40, 11};
        for (auto v : inserts)
            bt1.insert(v);
        cout << bt1 << '\n';
        
        int removes[] = { 10, 40, 22, -1, 92, 4};
        for (auto v : removes)
            bt1.remove(v);
        cout << bt1 << '\n';
        return 0;
    }
