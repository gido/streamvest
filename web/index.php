<?php
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

require_once __DIR__.'/../vendor/autoload.php';

// Configure the app
$app = new Silex\Application();
$app['debug'] = getenv('DEBUG');

$app->register(new Silex\Provider\TwigServiceProvider(), [
    'twig.path' => __DIR__.'/../views',
    'twig.options' => [
    	'cache' => __DIR__.'/../cache/twig',
    ]
]);
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'translator.domains' => array(),
));
$app->register(new Silex\Provider\FormServiceProvider());

$app['csv_harvest'] = $app->share(function($app) {

	return new Streamvest\CsvHarvest();
});

// Define controller actions
$app->get('/', function () use ($app) {
	$csv = $app['csv_harvest'];
    return $app['twig']->render('index.html.twig');
});

$app->post('/upload', function(Request $request) use ($app) {
	$form = $app['form.factory']->createNamedBuilder('upload', 'form', null, array('csrf_protection' => false))
        ->add('file', 'file', [
        	'constraints' => [
        		new Assert\File([ 'maxSize' => '1024k', 'mimeTypes' => ['text/csv', 'text/plain']]),
        	],
        ])
        ->getForm();

    $form->handleRequest($request);
    if ($form->isValid()) {
        $data = $form->getData();
        $file = $data['file'];

        $hash = substr(md5_file($file->getPathname()), 0, 6);
        $output = __DIR__.'/uploads/'.$hash.'.csv';

        $app['csv_harvest']->transform($file->getPathname(), $output);

        // do something with the data
        return $app->json(['success' => true, 'hash' => $hash]);
    } else {

    	return $app->json(['success' => false, 'errorMessage' => $form->getErrorsAsString()], 400);
    }
});

$app->get('/{hash}', function ($hash) use ($app) {

    return $app['twig']->render('steamgraph.html.twig', [
    	'hash' => $hash,
    ]);
});

$app->run();
