# Fix #68-69

You need to edit the function ""getFIelds" (used to sort lists) in all controller extending the CrudController.

Before:


```
protected function getFields()
{
    return array(
        'id'   => 'ID',
        'name' => 'Name',
        'menu' => 'Menu',
    );
}
```

After:

```
protected function getFields()
{
    return array(
        'id'   => array(
            'label' => 'ID',
        ),
        'name' => array(
            'label' => 'Name',
        ),
        'menu' => array(
            'label' => 'Menu',
            'sort'  => 'm.name',
            'join'  => 'm'
        )
    );
}
```

In this example we are sorting items of a menu, the column "menu" is a relationship with "item".
If you want to sort using "menu" you need to add the key "sort" inside the array of the getFields().

In the function "doIndex" of the CrudController we added this join so you can sort using the menu column.

```
foreach ($this->getFields() as $key => $field) {
    if (isset($field['join'])) {
        $queryBuilder
            ->leftJoin('e.'.$key, $field['join']);
    }
}
```

Where $key (here menu) is the attribute name of the "item" entity and $field['join'] is the alias of the join.

# Fix 11
---

To implement the filters you need to add this function in your controller
```php
/**
 * Get filters
 *
 * @return array
 */
protected function getFilters()
{
    return array();
}
```
By default, it returns an empty array.

---
##Types
---
###1.Common parameters

placeholder: label use in form render

name: name of the input or select generated

type: the field type

options: array of options (different for each type)

###2.Entity

You can define a filter on an Entity

```php
protected function getFilters()
{
    return array(
        array(
            'placeholder' => 'Director',
            'name'        => 'director',
            'type'        => 'entity',
            'options' => array(
                'class'    => 'SandboxCastingBundle:Director',
                'property' => 'fullName',
                'relation' => 'director'
            )
        )
    );
}
```

mandatory options:

class: namespace of class (ie AcmeDemoBundle:Acme

property: attribute to display in form render

relation: relation between the reference entity and this entity

###3.Choice

This type is used to generate a select with specifics fields

```php
protected function getFilters()
{
    return array(
        array(
            'placeholder' => 'Active',
            'name'        => 'active',
            'type'        => 'choice',
            'options' => array(
                'choices'    => array(
                    '1' => 'Yes',
                    '0' => 'No'
                ),
                'property' => 'active'
            )
        ),
    );
}
```

mandatory options:

choices: array of choices ('value' => 'label')

property: attribute to mapped for filter items


###4.Referer

This type referes to the reference entity.
You can diplay it on a select field (type: choice) or on an input field (type: text).


```php
protected function getFilters()
{
    return array(
        // display a select
        array(
            'placeholder' => 'Name',
            'name'        => 'name',
            'type'        => 'referer',
            'options' => array(
                'type'     => 'choice',
                'property' => 'name'
            )
        ),
        // display a text input
        array(
            'placeholder' => 'Name',
            'name'        => 'name',
            'type'        => 'referer',
            'options' => array(
                'property' => 'name'
            )
        ),
    );
}
```
mandatory options:

property: attribute to mapped for filter items

optional:

If the type in options array is defined to choice, a select is display.

If you specify nothing or text a input is display.

###5.Search

This type can search in an group of fields.



```php
protected function getFilters()
{
    return array(
        array(
            'placeholder' => 'Search',
            'name'        => 'search',
            'type'        => 'search',
            'options' => array(
                'properties' => array(
                    'name',
                    'director.fullName'
                )
            )
        ),
    );
}
```
mandatory options:

properties: array of attributes to search in