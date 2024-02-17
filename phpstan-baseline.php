<?php declare(strict_types = 1);

$ignoreErrors = [];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'\\$ref\' on mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/ReferenceContext.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'path\' on array\\{scheme\\?\\: string, host\\?\\: string, port\\?\\: int\\<0, 65535\\>, user\\?\\: string, pass\\?\\: string, path\\?\\: string, query\\?\\: string, fragment\\?\\: string\\}\\|false\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/ReferenceContext.php',
];
$ignoreErrors[] = [
	'message' => '#^Method openapiphp\\\\openapi\\\\ReferenceContext\\:\\:resolveReferenceData\\(\\) should return array\\<string, mixed\\>\\|openapiphp\\\\openapi\\\\SpecObjectInterface\\|string\\|null but returns mixed\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/ReferenceContext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$data of class openapiphp\\\\openapi\\\\spec\\\\Reference constructor expects array\\<string, mixed\\>, mixed given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/ReferenceContext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$parts of method openapiphp\\\\openapi\\\\ReferenceContext\\:\\:buildUri\\(\\) expects array\\<string, string\\>, array\\<string, int\\<0, 65535\\>\\|string\\> given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/ReferenceContext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$parts of method openapiphp\\\\openapi\\\\ReferenceContext\\:\\:buildUri\\(\\) expects array\\<string, string\\>, array\\<string, int\\<0, 65535\\>\\|string\\|null\\> given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/ReferenceContext.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$parts of method openapiphp\\\\openapi\\\\ReferenceContext\\:\\:buildUri\\(\\) expects array\\<string, string\\>, array\\<string, int\\|string\\>\\|false given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/ReferenceContext.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \'\' on mixed\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/ReferenceContextCache.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset string on mixed\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/ReferenceContextCache.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#2 \\$array of function array_key_exists expects array, mixed given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/ReferenceContextCache.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot access offset \\(int\\|string\\) on mixed\\.$#',
	'count' => 9,
	'path' => __DIR__ . '/src/SpecBaseObject.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @return with type mixed is not subtype of native type object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/SpecBaseObject.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method resolveReferences\\(\\) on openapiphp\\\\openapi\\\\spec\\\\PathItem\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Callback.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method setDocumentContext\\(\\) on openapiphp\\\\openapi\\\\spec\\\\PathItem\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Callback.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method setReferenceContext\\(\\) on openapiphp\\\\openapi\\\\spec\\\\PathItem\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Callback.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @return with type mixed is not subtype of native type object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Callback.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$data of class openapiphp\\\\openapi\\\\spec\\\\PathItem constructor expects array\\<string, mixed\\>, mixed given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Callback.php',
];
$ignoreErrors[] = [
	'message' => '#^Access to an undefined property object\\:\\:\\$\\$ref\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/PathItem.php',
];
$ignoreErrors[] = [
	'message' => '#^Cannot call method resolveReferences\\(\\) on array\\|openapiphp\\\\openapi\\\\SpecObjectInterface\\|string\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/spec/PathItem.php',
];
$ignoreErrors[] = [
	'message' => '#^Method openapiphp\\\\openapi\\\\spec\\\\Reference\\:\\:resolve\\(\\) should return array\\|openapiphp\\\\openapi\\\\SpecObjectInterface\\|string\\|null but returns mixed\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Reference.php',
];
$ignoreErrors[] = [
	'message' => '#^Method openapiphp\\\\openapi\\\\spec\\\\Reference\\:\\:resolveTransitiveReference\\(\\) return type has no value type specified in iterable type array\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Reference.php',
];
$ignoreErrors[] = [
	'message' => '#^Method openapiphp\\\\openapi\\\\spec\\\\Reference\\:\\:resolveTransitiveReference\\(\\) should return array\\|openapiphp\\\\openapi\\\\SpecObjectInterface\\|null but returns array\\|openapiphp\\\\openapi\\\\SpecObjectInterface\\|string\\|null\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Reference.php',
];
$ignoreErrors[] = [
	'message' => '#^PHPDoc tag @return with type mixed is not subtype of native type object\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Reference.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$baseDocument of method openapiphp\\\\openapi\\\\DocumentContextInterface\\:\\:setDocumentContext\\(\\) expects openapiphp\\\\openapi\\\\SpecObjectInterface, openapiphp\\\\openapi\\\\SpecObjectInterface\\|null given\\.$#',
	'count' => 1,
	'path' => __DIR__ . '/src/spec/Reference.php',
];
$ignoreErrors[] = [
	'message' => '#^Parameter \\#1 \\$referencedDocument of method openapiphp\\\\openapi\\\\spec\\\\Reference\\:\\:adjustRelativeReferences\\(\\) expects array\\<string, mixed\\>, mixed given\\.$#',
	'count' => 2,
	'path' => __DIR__ . '/src/spec/Reference.php',
];

return ['parameters' => ['ignoreErrors' => $ignoreErrors]];
