Class: Request.API
==================

### Extends:

[Request](/mootools-core/Request/Request.JSON)

### Syntax:

	var req = new Request.API([options]);
	
### Arguments:

1. options - (*object*, optional) See below.

### Options:

* link  - (*boolean*: defaults to 'cancel').


Method: encode
--------------

Encodes the specified pattern and args as an API route.

### Syntax:

	var apiRoute = Request.API.encode(pattern[, args]);
	
### Arguments:

	1. pattern - (string) The API route pattern.
	2. args - (obj) The arguments for the pattern and operation.
	
### Returns:

(string) A API route.
