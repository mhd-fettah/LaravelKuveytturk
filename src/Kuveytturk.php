<?php

/**
 * Description of Kuveytturk.php
 *
 * @author Ali Gençsoy <mail@aligencsoy.com>
 */

namespace AliGencsoy\LaravelKuveytturk;

class Kuveytturk extends KuveytturkBase {
	/**
	 * securePayment is first step in payment process
	 *
	 * @return string HTML format
	 */
	public function securePayment() {
		$xml = <<<EOT
			<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchemainstance" xmlns:xsd="http://www.w3.org/2001/XMLSchema"> 
				<APIVersion>{$this->getApiVersion()}</APIVersion>
				<OkUrl>{$this->getOkUrl()}</OkUrl>
				<FailUrl>{$this->getFailUrl()}</FailUrl>
				<HashData>{$this->hashData()}</HashData>
				<MerchantId>{$this->getMerchantId()}</MerchantId>
				<CustomerId>{$this->getCustomerId()}</CustomerId>
				<UserName>{$this->getUsername()}</UserName>
				<CardNumber>{$this->getCardNumber()}</CardNumber>
				<CardExpireDateYear>{$this->getCardExpireDateYear()}</CardExpireDateYear>
				<CardExpireDateMonth>{$this->getCardExpireDateMonth()}</CardExpireDateMonth>
				<CardCVV2>{$this->getCardCvv2()}</CardCVV2>
				<CardHolderName>{$this->getCardHolderName()}</CardHolderName>
				<CardType>{$this->getCardType()}</CardType>
				<BatchID>{$this->getBatchId()}</BatchID>
				<TransactionType>{$this->getTransactionType()}</TransactionType>
				<InstallmentCount>{$this->getInstallmentCount()}</InstallmentCount>
				<Amount>{$this->getAmount()}</Amount>
				<DisplayAmount>{$this->getDisplayAmount()}</DisplayAmount>
				<CurrencyCode>{$this->getCurrencyCode()}</CurrencyCode>
				<MerchantOrderId>{$this->getMerchantOrderId()}</MerchantOrderId>
				<TransactionSecurity>{$this->getTransactionSecurity()}</TransactionSecurity>
			</KuveytTurkVPosMessage>
EOT;

		$result = $this->request($xml, $this->getSecurePaymentUrl());
		if(trim($result) === 'TechnicalException') {
			dd('Something wrong with securePayment request data');
		}

		return $result;
	}

	/**
	 * paymentConfirmation is second step in payment process
	 *
	 * @param mixed $response
	 *
	 * @return self
	 */
	public function paymentConfirmation() {
		$this->setOkUrl(null);
		$this->setFailUrl(null);

		$xml = <<<EOT
			<KuveytTurkVPosMessage xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">
				<APIVersion>{$this->getApiVersion()}</APIVersion>
				<HashData>{$this->hashData()}</HashData>
				<MerchantId>{$this->getMerchantId()}</MerchantId>
				<CustomerId>{$this->getCustomerId()}</CustomerId>
				<UserName>{$this->getUsername()}</UserName>
				<TransactionType>{$this->getTransactionType()}</TransactionType>
				<InstallmentCount>{$this->getInstallmentCount()}</InstallmentCount>
				<Amount>{$this->getAmount()}</Amount>
				<MerchantOrderId>{$this->getMerchantOrderId()}</MerchantOrderId>
				<TransactionSecurity>{$this->getTransactionSecurity()}</TransactionSecurity>
				<KuveytTurkVPosAdditionalData>
					<AdditionalData>
						<Key>MD</Key>
						<Data>{$this->getMd()}</Data>
					</AdditionalData>
				</KuveytTurkVPosAdditionalData>
			</KuveytTurkVPosMessage>
EOT;

		$result = $this->request($xml, $this->getPaymentConfirmationUrl());
		$this->parseResponse($result);

		return $this;
	}

	/**
	 * @param mixed $response
	 *
	 * @return self
	 */
	public function parseResponse($responseRaw) {
		if(gettype($responseRaw) === 'string') {
			$responseRaw = [ 'AuthenticationResponse' => $responseRaw ];
		}

		$response = $responseRaw;

		if(gettype($response) === 'object') {
			if(get_class($response) !== 'Illuminate\Http\Request') {
				dd('LaravelKuveytturk not a Illuminate\Http\Request');
			}

			$response = $response->all();
		}

		if(gettype($response) === 'array' && !array_key_exists('AuthenticationResponse', $response)) {
			dd('LaravelKuveytturk not a virtual pos response');
		}

		$xml = $response['AuthenticationResponse'];
		$xml = urldecode($xml);
		$xml = simplexml_load_string($xml);
		$xml = json_decode(json_encode($xml));

		$this->setRaw(urldecode($responseRaw['AuthenticationResponse']));
		$this->setXml(simplexml_load_string($responseRaw['AuthenticationResponse']));

		if($xml->ResponseCode === '00') {
			$this->setError(false);
			$this->setMd($xml->MD);
			$this->setHashData($xml->HashData);
			$this->setBatchId($xml->VPosMessage->BatchID);
			$this->setInstallmentCount($xml->VPosMessage->InstallmentCount);
			$this->setAmount(intval($xml->VPosMessage->Amount) / 100);
			$this->setCancelAmount(intval($xml->VPosMessage->CancelAmount) / 100);
			$this->setProvisionNumber($xml->ProvisionNumber);
			$this->setRrn($xml->RRN);
			$this->setOrderId($xml->OrderId);
			$this->setStan($xml->Stan);
		} else {
			$this->setError(true);
		}

		$this->setResponseCode($xml->ResponseCode);
		$this->setResponseMessage($xml->ResponseMessage);
		$this->setMerchantOrderId($xml->MerchantOrderId);
		$this->setReferenceId($xml->ReferenceId);
		$this->setBusinessKey($xml->BusinessKey);

		return $this;
	}

	/**
	 * hashData is helper
	 *
	 * @return string
	 */
	public function hashData() {
		$hashedPassword = $this->getPassword();
		$hashedPassword = sha1($hashedPassword, 'ISO-8859-9');
		$hashedPassword = base64_encode($hashedPassword);

		$hashData = [
			$this->getMerchantId(),
			$this->getMerchantOrderId(),
			$this->getAmount(),
			$this->getOkUrl(),
			$this->getFailUrl(),
			$this->getUsername(),
			$hashedPassword
		];
		$hashData = implode('', $hashData);

		$hashData = sha1($hashData, 'ISO-8859-9');
		$hashData = base64_encode($hashData);

		return $hashData;
	}

	/**
	 * @param string $xml
	 *
	 * @return string
	 */
	public function request($xml, $url) {
		while(mb_strpos($xml, "\t") !== false) {
			$xml = mb_ereg_replace("\t", '', $xml);
		}
		$xml = trim($xml);

		try {
			$ch = curl_init();

			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-type: application/xml', 'Content-length: ' . strlen($xml) ] );
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$data = curl_exec($ch);
			curl_close($ch);
		} catch (\Exception $e) {
			echo 'Caught exception: ', $e->getMessage(), "\n";
		}

		return $data;
	}
}
