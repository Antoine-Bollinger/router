# Abollinger/Router

This router is used in the partez framework (see on **[Packagist](https://packagist.org/packages/abollinger/partez)**). It provides a set of functions to help you create a smart router for you web app or API.

[![Total Downloads](https://img.shields.io/packagist/dt/abollinger/router)](https://packagist.org/packages/abollinger/router)
[![Latest Stable Version](https://img.shields.io/packagist/v/abollinger/router)](https://packagist.org/packages/abollinger/router)
[![License](https://img.shields.io/packagist/l/abollinger/router)](https://packagist.org/packages/abollinger/router)

## Getting started

### Installation

You can install the router in your project using composer:

```bash
composer require abollinger/router
```

## Usage 

Here a the functions provided:

<details open="open">
    <summary>Functions in the Classes tree</summary>
    <ul>
        <li>Abollinger/
            <ul>
                <li>Router::
                    <ul>
                        <li><a href="#getRoutesFromYaml">getRoutesFromYaml($dir)</a></li>
                        <li><a href="#getRoutesFromDirectory">getRoutesFromDirectory($dir)</a></li>
                    </ul>
                </li>
            </ul>
        </li>
    </ul>    
</details>

### getRoutesFromYaml

Retrieves routes from YAML files present in the specified directory.
* ```@param string $dir```: The directory containing YAML route files
* ```@return array```: An array of parsed routes from YAML files

### getRoutesFromDirectory

Retrieves routes from PHP controller files within the specified directory.
* ```@param string $directory```: The directory path containing PHP controller files.
* ```@return array```: An array containing extracted routes with their path, name, and controller information.

## Classes tree

```bash
Abollinger/
└── Router::
    ├── getRoutesFromYaml($dir)
    └── getRoutesFromDirectory($dir)
```

