<?php
namespace Bits4breakfast\Zephyros;

use Aws\Common\Aws;

class ServiceBus {
	protected $container = null;
	protected $sns = null;
	
	public function __construct( ServiceContainer $container ) {
		$this->container = $container;
	}

	public function emit( $event, $payload = null ) {
		$config = $this->container->config();

		if ( empty($event) ) {
			throw new \Exception("Type cannot be empty");
		}
			
		$routing_table = $config->get('service_bus.routing_table');
		if ( !isset($routing_table[$event]) ) {
			throw new \Exception(
				"No topics found for key ".$event.", envelope is in details ".json_encode($payload)
			);
		}

		$message = array( 
			'type' => $event,
			'payload' => $payload
		);

		foreach ( $routing_table[$event] as $topic ) {
			if ( false && DEV_ENVIRONMENT ) {
				$id = uniqid();
	
				$fakeSnsMessage = array(
					"Type" => "Notification",
					"MessageId" => $id,
					"TopicArn" => $topic,
					"Message" => json_encode($message),
					"Timestamp" => "2012-09-20T09:46:11.882Z",
					"SignatureVersion" => "1",
					"Signature" => "RR0E9bFpvP7i8LMIJB4PqX56ITXZUBMBW0tMWGzFv0fjAU/RHSjeF+ewLu6sGQDvre96r9zMCaYzTU20PJv9pFA0/YjGJZw0KCbi1Dnk/hY055miZg9BvyCJ3pJ6WmfHjGPU5vlBzQoEQyUgz3jUBMDXgYuaLtV3NxGCx4CSreQ=",
					"SigningCertURL" => "https://sns.eu-west-1.amazonaws.com/SimpleNotificationService-f3ecfb7224c7233fe7bb5f59f96de52f.pem",
					"UnsubscribeURL" => "https://sns.eu-west-1.amazonaws.com/?Action=Unsubscribe&SubscriptionArn=arn:aws:sns:eu-west-1:252473489437:com_youmpa_prod_notifications_actions:2c6f72fd-6718-4826-8deb-4cc93e1d6e0b",
					"SubscriptionArn" => "arn:aws:sns:eu-west-1:252473489437:com_youmpa_prod_notifications_actions:2c6f72fd-6718-4826-8deb-4cc93e1d6e0b"
				);
	
				$folder = sprintf("%s/sns_simulator", \Config::TEMP_PATH);
				if (!is_dir($folder))
					mkdir($folder);
	
				$file = sprintf("%s/%s.msg", $folder, $id);
				file_put_contents($file, json_encode($fakeSnsMessage));
			} else {
				if ( $this->sns === null ) {
					$this->sns = Aws::factory( \Config::BASE_PATH.'/application/config/aws.config.php' )->get('sns');
				}

				$this->sns->publish( array(
					'TopicArn' => $topic,
					'Message' => json_encode($message)
				));
			}
		}
	}
}