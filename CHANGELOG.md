CHANGELOG
=========

1.0.3 (2018-09-13)
------------------

* added message builder function to SlackWebhook

1.0.2 (2018-08-28)
------------------

* make buildJsonPayload public so we can separate the payload generation from the sending process
* split send function into two public functions send and sendPayload - where send simply calls sendPayload with a built
  payload
* send/sendPayload now actually returns the \Psr\Http\Message\ResponseInterface we said it would
* swap to using actual Guzzle\Client rather than Guzzle\ClientInterface 

1.0.1 (2018-08-27)
------------------

* bug fix - don't fall over when we don't have any fields

1.0.0 (2018-08-27)
------------------

* initial release
