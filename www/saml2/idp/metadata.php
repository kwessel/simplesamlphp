<?php

require_once('../../_include.php');

require_once('SimpleSAML/Utilities.php');
require_once('SimpleSAML/Session.php');
require_once('SimpleSAML/Metadata/MetaDataStorageHandler.php');
require_once('SimpleSAML/XHTML/Template.php');

require_once('xmlseclibs.php');

/* Load simpleSAMLphp, configuration and metadata */
$config = SimpleSAML_Configuration::getInstance();
$metadata = SimpleSAML_Metadata_MetaDataStorageHandler::getMetadataHandler();
$session = SimpleSAML_Session::getInstance(true);

if (!$config->getValue('enable.saml20-idp', false))
	SimpleSAML_Utilities::fatalError($session->getTrackID(), 'NOACCESS');




try {

	$idpmeta = isset($_GET['idpentityid']) ? $_GET['idpentityid'] : $metadata->getMetaDataCurrent('saml20-idp-hosted');
	$idpentityid = isset($_GET['idpentityid']) ? $_GET['idpentityid'] : $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
	
	$publiccert = $config->getBaseDir() . '/cert/' . $idpmeta['certificate'];

	if (!file_exists($publiccert)) 
		throw new Exception('Could not find certificate [' . $publiccert . '] to attach to the authentication resposne');
	
	$cert = file_get_contents($publiccert);
	$data = XMLSecurityDSig::get509XCert($cert, true);
	
	
	$metaflat = "
	'" . htmlspecialchars($idpentityid) . "' =>  array(
		'name'                 => 'Type in a name for this entity',
		'description'          => 'and a proper description that would help users know when to select this IdP.',
		'SingleSignOnService'  => '" . htmlspecialchars($metadata->getGenerated('SingleSignOnService', 'saml20-idp-hosted')) . "',
		'SingleLogoutService'  => '" . htmlspecialchars($metadata->getGenerated('SingleLogoutService', 'saml20-idp-hosted')) . "',
		'certFingerprint'      => '" . strtolower(sha1(base64_decode($data))) ."'
	),
";
	
	$metaxml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
	<EntityDescriptor xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance" xmlns="urn:oasis:names:tc:SAML:2.0:metadata"
 entityID="' . htmlspecialchars($idpentityid) . '">
    <IDPSSODescriptor
        WantAuthnRequestsSigned="false"
        protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
        
		<KeyDescriptor use="signing">
			<ds:KeyInfo xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
				<ds:X509Data>
					<ds:X509Certificate>' . htmlspecialchars($data) . '</ds:X509Certificate>
				</ds:X509Data>
			</ds:KeyInfo>
		</KeyDescriptor>  
        

        
        <!-- Logout endpoints -->
        <SingleLogoutService
            Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
            Location="' . htmlspecialchars($metadata->getGenerated('SingleLogoutService', 'saml20-idp-hosted')) . '"
            ResponseLocation="' . htmlspecialchars($metadata->getGenerated('SingleLogoutService', 'saml20-idp-hosted')) . '"
            index="0" 
            isDefault="true"
            />

        
        <!-- Supported Name Identifier Formats -->
        <NameIDFormat>urn:oasis:names:tc:SAML:2.0:nameid-format:transient</NameIDFormat>
        
        <!-- AuthenticationRequest Consumer endpoint -->
        <SingleSignOnService
            Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
            Location="' . htmlspecialchars($metadata->getGenerated('SingleSignOnService', 'saml20-idp-hosted')) . '"
            index="0" 
            isDefault="true"
            />
        
    </IDPSSODescriptor>
</EntityDescriptor>';
	
	
	if (array_key_exists('output', $_GET) && $_GET['output'] == 'xml') {
		header('Content-Type: application/xml');
		
		echo $metaxml;
		exit(0);
	}


	$defaultidp = $config->getValue('default-saml20-idp');
	
	$t = new SimpleSAML_XHTML_Template($config, 'metadata.php');
	

	$t->data['header'] = 'SAML 2.0 IdP Metadata';
	$t->data['metaurl'] = SimpleSAML_Utilities::addURLparameter(SimpleSAML_Utilities::selfURLNoQuery(), 'output=xml');
	$t->data['metadata'] = htmlentities($metaxml);
	$t->data['metadataflat'] = htmlentities($metaflat);
	$t->data['defaultidp'] = $defaultidp;
	$t->show();
	
} catch(Exception $exception) {
	
	SimpleSAML_Utilities::fatalError($session->getTrackID(), 'METADATA', $exception);

}

?>