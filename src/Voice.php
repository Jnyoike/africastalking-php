<?php

namespace AfricasTalking;

class Voice extends Service
{
    private $xmlString;

    public function __call($method, $args)
    {
        // First check if method exists
        if (method_exists($this, 'build' . $method)) {
            return $this->stringBuilder('build'. $method, $args);
        } else if (method_exists($this, 'do' . $method)) {
            return $this->apiCall('do' . $method, $args);
        } else {
            return $this->error($method .' is an invalid Voice SDK Method');
        }
    }

    private function stringBuilder($method, $args)
    {
        $result = $this->$method($args);

        if (empty($this->xmlString)) {
            $this->xmlString = $result;
        } else {
            $this->xmlString .= $result;
        }

        return $this;
    }

    private function apiCall($method, $args)
    {
        if (!isset($args[0])) {
            $args = [0 => ''];
        }
        return $this->$method($args[0]);
    } 

    /**
     * Builds XML string from chained voice actions
     *
     * @return string
     */
    public function build()
    {
        if (empty($this->xmlString)) {
            return null;
        }
        return $this->xmlString;
    }
    
    protected function doCall($options)
    {
		if (!isset($options['to']) || !isset($options['from'])) {
			return $this->error('The parameters to and from must be defined');
        }

        // Validate callTo
        $checkCallTo = strpos($options['to'], '+');
        if ($checkCallTo === false || $checkCallTo !== 0) {
            return $this->error('callTo must be in the format \'+2XXYYYYYYYYY\'');
        }
        
        // Validate callFrom
        $checkCallFrom = strpos($options['from'], '+');
        if ($checkCallFrom === false || $checkCallFrom !== 0) {
            return $this->error('callFrom must be in the format \'+2XXYYYYYYYYY\'');
        }


        $requestData = [
            'username' => $this->username,
            'to' => $options['to'],
            'from' => $options['from']
        ];

		$response = $this->client->post('call', ['form_params' => $requestData ] );

		return $this->success($response);
    }

    protected function doUploadMediaFile($options)
    {
		if (!isset($options['phoneNumber'])) {
			return $this->error('Phone number must be defined');
        }
        $phoneNumber = $options['phoneNumber'];
        
        if (!isset($options['url'])) {
            return $this->error('url must be defined');
        }
        $url = $options['url'];
        
        // Check if valid URL passed
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return $this->error('URL not valid');
        }

        $requestData = [
            'username' => $this->username,
            'phoneNumber' => $phoneNumber,
            'url' => $url
        ];

        $response = $this->client->post('mediaUpload', ['form_params' => $requestData]);

        return $this->success($response);
    }

    protected function dofetchQueuedCalls($phoneNumber)
    {
        // Check and validate phoneNumber
        if (empty($phoneNumber)) {
            return $this->error('Phone number is required and must be in the format \'+2XXYYYYYYYYY\'');            
        }
        
        $checkPhoneNumber = strpos($phoneNumber, '+');
        if ($checkPhoneNumber === false || $checkPhoneNumber != 0) {
            return $this->error('Phone number must be in the format \'+2XXYYYYYYYYY\'');
        }

        $requestData = [
            'username' => $this->username, 
            'phoneNumbers' => $phoneNumber
        ];

        $response = $this->client->post('queueStatus', ['form_params' => $requestData]);
        return $this->success($response);
                
    }

    protected function buildSay($options)
    {

        // Check for text 
        if (!isset($options['text'])) {
            return $this->error('Please set text to be read out');
        }
        $text = $options['text'];

        // Check if read out voice has been set
        if (!isset($options['voice'])) {
            $voice = 'man';
        }
        $voice = $options['voice'];

        // Check if playBeep option has been set
        if (!isset($options['playBeep']) || !is_bool($options['playBeep'])) {
            $playBeep = false;
        }
        $playBeep = $options['playBeep'];

        $sayString = '<Say voice="' . $voice . '" playBeep="'. $playBeep .'" >'. $text .'</Say>';
        
        return $sayString;
    }

    protected function buildPay($url)
    {
        if (!$this->isValidURL($url))
            return $this->error('Play URL is not valid');

        $playString = '<Play url="'. $url . '" />';

        return $playString;
    }

    protected function buildGetDigits($options) {

        // Check for text 
        if (!isset($options['text'])) {
            return $this->error('Please set text to be read out');
        }
        $text = $options['text'];

        // Check if URL or Text is set
        $url = $options['url'];

        // Get number of digits
        if(isset($options['numDigits'])) {
            $numDigits = $options['numDigits'];
            if (!is_numeric($numDigits)) {
                return $this->error('Please set a number value for the timeout');
            }
        }

        // Get timeout
        if(isset($options['timeout'])) {
            $timeout = $options['timeout'];
            if (!is_numeric($timeout)) {
                return $this->error('Please set a number value for the timeout');
            }
        }

        // Get finishOnKey
        $finishOnKey = $options['finishOnKey'];
        
        // Get callbackURL
        $callBackUrl = $options['callBackUrl'];
        if (!$this->isValidURL($callBackUrl)) {
            return $this->error('Please set a valid callback URL');
        }
        
        
        // -- NOW TO BUILD STRING

        // Build opening tag
        $getDigitsString = '<GetDigits ';
        if (!empty($finishOnKey)) {
            $getDigitsString .= ' finishOnKey="'. $finishOnKey .'"';
        }
        if (!empty($timeout)) {
            $getDigitsString .= ' timeout="'. $timeout .'"';
        }
        if (!empty($numDigits)) {
            $getDigitsString .= ' numDigits="'. $numDigits .'"';
        }
        if (!empty($callBackUrl)) {
            $getDigitsString .= ' callBackUrl="'. $callBackUrl .'"';
        }
        $getDigitsString .= '>';

        // ... add child element
        if (!empty($text)) {
            $getDigitsString .= $this->buildSay($text);
        }

        if (!empty($url)) {
            $getDigitsString .= $this->buildPlay($url);
        }

        $getDigitsString .= '</GetDigits>';

        return $getDigitsString;

    }


    protected function buildDial($options)
    {
        // ringbackTone: String, record: Boolean, sequential: Boolean, callerId: String, maxDuration: Integer)

        // Validate phoneNumber
        if (!isset($options['phoneNumbers'])) {
            return $this->error('Please specifiy at least one number to dial');
        } else {
            $phoneNumbers = $options['phoneNumbers'];
            $numbers = explode(',', $phoneNumbers);
            
            foreach ($numbers as $num) {
                $num = strpos($num, '+');
                if ($num === false || $num == 0) {
                    return $this->error('Phone number must be in the format \'+2XXYYYYYYYYY\'');
                }
            }
        }

        // Check if ringback tone is set
        if (!isset($options['ringBackTone'])) {
            if (!$this->isValidURL($ringBackTone)) {
                return $this->error('Ringbacktone not a valid URL');
            }
        }
        $ringBackTone = $options['ringBackTone'];

        // Check if record is set
        if (!isset($options['record']) || !is_bool($options['record'])) {
            $record = false;
        } else {
            $record = $options['record'];
        }

        // Check if sequential
        if (!isset($options['sequential']) || !is_bool($options['sequential'])) {
            $sequential = false;
        } else {
            $sequential = $options['sequential'];
        }

        // Check if callerId is set
        if (!isset($options['callerId']) || !is_bool($callerId)) {
            $calledId = false;
        } else {
            $callerId = $options['callerId'];
        }

        // Check if maxDuration is set
        if(isset($options['maxDuration'])) {
            $maxDuration = $options['maxDuration'];
            if (!is_integer($maxDuration) || $maxDuration < 0) {
                return $this->error('Max duration must be an integer value');
            }
        }

        $dialString = '<Dial phoneNumbers="'. $phoneNumbers . '"';
        if (!empty($record)) {
            $dialString .= ' record="'. $record .'"';
        }
        if (!empty($sequential)) {
            $dialString .= ' sequential="'. $sequential .'"';
        }
        if (!empty($callerId)) {
            $dialString .= ' callerId="'. $callerId .'"';
        }
        if (!empty($ringBackTone)) {
            $dialString .= ' ringBackTone="'. $ringBackTone .'"';
        }
        if (!empty($maxDuration)) {
            $dialString .= ' maxDuration="'. $maxDuration .'"';
        }
        $dialString .= '>';


        return '<Response>' . $dialString . '</Response>';
        
    }


    protected function buildRecord($options)
    {

        /** Terminal Recording **/

        if (empty($options)) {
            return '<Response><Record /></Response>';            
        }
        
        /** Partial Recording **/

        // Get finishOnKey
        $finishOnKey = $options['finishOnKey'];

        // Get Max Length
        if (isset($options['maxLength'])) {
            $maxLength = $options['maxLength'];
            if (!is_numeric($maxLength)) {
                return $this->error('Please set a number value for the timeout');
            }
        }

        // Get timeout
        if (isset($options['timeout'])) {
            $timeout = $options['timeout'];
            if (!is_numeric($timeout)) {
                return $this->error('Please set a number value for the timeout');
            }
        }
        
        // Check if trimSilence is set
        if (isset($options['trimSilence']) || !is_bool($options['trimSilence'])) {
            $trimSilence = false;
        } else {
            $trimSilence = $options['trimSilence'];
        }
        
        // Check if playBeep option has been set
        if (!isset($options['playBeep']) || !is_bool($options['playBeep'])) {
            $playBeep = false;
        } else {
            $playBeep = $options['playBeep'];
        }

        // Get callbackURL
        $callBackUrl = $options['callBackUrl'];
        if (!$this->isValidURL($callBackUrl)) {
            return $this->error('Please set a valid callback URL');
        }

        // Build opening tag
        $recordString = '<Record';
        if (!empty($finishOnKey)) {
            $recordString .= ' finishOnKey="'. $finishOnKey .'"';
        }
        if (!empty($maxLength)) {
            $recordString .= ' maxLength="'. $maxLength .'"';
        }
        if (!empty($timeout)) {
            $recordString .= ' timeout="'. $timeout .'"';
        }
        if (!empty($trimSilence)) {
            $recordString .= ' trimSilence="'. $trimSilence .'"';
        }
        if (!empty($playBeep)) {
            $playBeep .= ' playBeep="'. $playBeep .'"';
        }
        if (!empty($callBackUrl)) {
            $getDigitsString .= ' callBackUrl="'. $callBackUrl .'"';
        }
        $recordString .= '>';

        
    }

    protected function buildEnqueue($options)
    {
        // Check if holdMusic option has been set
        if (isset($options['holdMusic'])) {
            $holdMusic = $options['holdMusic'];
            if (!$this->isValidURL($holdMusic)) {
                return $this->error('Please set a valid URL value for holdMusic');
            }
        }

        // Build opening tag
        $enqueueString = '<Enqueue';
        if (!empty($holdMusic)) {
            $enqueueString .= ' holdMusic="'. $HoldMuisic .'"';
        }

        if (isset($options['name'])) {
            $name = $options['name'];
            $enqueueString .= ' name="'. $name .'"';
        }
        $enqueueString .= '>';
        
        return '<Response>' . $enqueueString . '</Response>';
        
    }

    protected function buildDequeue($options)
    {
        // Check if holdMusic option has been set
        if (!isset($options['phoneNumber'])) {
            return $this->error('Please enter a valid phone number');
        }
        $phoneNumber = $options['phoneNumber'];

        
        // Build opening tag
        $dequeueString = '<Dequeue phoneNumber="'. $phoneNumber . '"';
        if (isset($options['name'])) {
            $name = $options['name'];
            $dequeueString .= ' name="'. $name .'"';
        }
        $dequeueString .= '>';
        
        return '<Response>' . $dequeueString . '</Response>';
        
    }

    protected function buildConference()
    {
        return '<Response><Conference /></Response>';
    }

    protected function buildRedirect($url)
    {
        return '<Response><Redirect>'. $url.'</Redirect></Response>';
    }

    protected function buildReject($url)
    {
        return '<Response><Reject /></Response>';
    }

    private function isValidURL($url)
    {   
        // If no url passed then it can't be valid
        if (empty($url)) {
            return false;
        }
        return !filter_var($url, FILTER_VALIDATE_URL) === false;
    }
}