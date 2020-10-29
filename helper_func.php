<?php 
// output json data
function json_error(string $message)
{
    // PRINT out
    echo json_encode(['status' => 'error', 'message' => $message], JSON_PRETTY_PRINT);
}

// all good
function json_success(string $message)
{
    // PRINT out
    echo json_encode(['status' => 'success', 'message' => $message], JSON_PRETTY_PRINT);
}

// print 
function json($message)
{
    // failed.
    if ($message == null) return json_error('Sorry this service is down. Please check back or contact support.');

    // Show message
    echo json_encode($message);
}

// en error occured
function json_has_error($class, array $data)
{
    // check json
    if ($class->json == null) :

        // log error
        ob_start();
        var_dump($class);
        $content = ob_get_contents();
        ob_clean();

        // ok add now
        $data['response_encoded'] = base64_encode($content); 

        // add time
        $data['time'] = time();

        // add raw text
        if (property_exists($class, 'message')) $data['message'] = $class->message;

        // for now let's log errors
        $fh = fopen(__DIR__ . '/errors.log', 'a+');
        fwrite($fh, json_encode($data, JSON_PRETTY_PRINT) . ",\n\n");
        fclose($fh);

    endif;
}