# Requirements Document

## Introduction

This project focuses on correcting the DPS (Declaração de Prestação de Serviços) XML generation in a WooCommerce WordPress plugin to comply with the official NFSe Nacional XSD schema. The current plugin is approximately 90% complete but generates XML that fails validation against the official DPS_v1.00.xsd schema. The goal is to fix the XML structure, ensure proper digital signature implementation, and provide automated XSD validation.

## Requirements

### Requirement 1: XML Structure and Namespace Compliance

**User Story:** As a developer integrating with NFSe Nacional, I want the generated DPS XML to use the correct namespace and structure, so that it validates against the official XSD schema.

#### Acceptance Criteria

1. WHEN generating DPS XML THEN the root element SHALL be `<DPS>` with namespace `xmlns="http://www.sped.fazenda.gov.br/nfse"`
2. WHEN creating the main block THEN it SHALL be `<infDPS Id="...">` with lowercase "inf"
3. WHEN setting the Id attribute THEN it SHALL be unique and used in digital signature reference (`Reference URI="#Id"`)
4. WHEN validating namespace THEN it SHALL NOT use the old namespace `http://www.nfse.gov.br/schema/dps_v1.xsd`

### Requirement 2: Identification Fields Mapping

**User Story:** As a tax compliance system, I want the DPS identification fields to match the XSD specification exactly, so that the document is properly recognized by government systems.

#### Acceptance Criteria

1. WHEN mapping identification fields THEN all current identification data SHALL be mapped to the correct XSD field names and structure
2. WHEN processing document numbers THEN they SHALL use the exact field names specified in the XSD schema
3. WHEN handling emission dates THEN they SHALL be formatted according to XSD datetime requirements
4. WHEN processing competence dates THEN they SHALL use the complete date format required by the XSD
5. WHEN setting environment indicators THEN they SHALL use the numeric codes defined in the XSD (Production/Homologation)
6. WHEN including emission type information THEN it SHALL follow the XSD enumeration values
7. WHEN adding process emission data THEN all required XSD fields SHALL be properly populated

### Requirement 3: Provider and Customer Identification

**User Story:** As a business using the plugin, I want provider and customer data to be correctly structured according to XSD requirements, so that the DPS is accepted by tax authorities.

#### Acceptance Criteria

1. WHEN identifying entities THEN use the direct document identification fields as specified in the XSD, avoiding generic inscription type/number combinations
2. WHEN mapping provider data THEN all provider information SHALL be structured according to the XSD provider element specification
3. WHEN mapping customer data THEN all customer information SHALL be structured according to the XSD customer element specification  
4. WHEN including address information THEN use the national address structure as defined in the XSD with all required fields
5. WHEN processing provider addresses THEN they SHALL include all mandatory address fields as specified in the XSD
6. WHEN processing customer addresses THEN they SHALL follow the same address structure requirements as providers
7. WHEN including contact information THEN phone numbers SHALL contain only digits as required by the XSD
8. WHEN including email addresses THEN they SHALL be valid plain text without HTML formatting

### Requirement 4: Service Information Structure

**User Story:** As a service provider, I want service details and tax calculations to be properly structured in the DPS XML, so that tax authorities can process the information correctly.

#### Acceptance Criteria

1. WHEN describing services THEN service description fields SHALL contain plain text without HTML tags as required by XSD
2. WHEN including service values THEN all monetary values SHALL be mapped to the correct XSD value fields with proper formatting
3. WHEN ISS tax applies THEN include the complete ISS tax group with all required fields as specified in the XSD
4. WHEN processing tax information THEN current tax structure SHALL be reorganized according to XSD tax grouping requirements
5. WHEN handling service descriptions THEN remove all HTML formatting to create clean plain text as required by XSD

### Requirement 5: Digital Signature Implementation

**User Story:** As a system administrator, I want the DPS to be digitally signed according to government standards, so that it's accepted by production systems.

#### Acceptance Criteria

1. WHEN signing DPS THEN target SHALL be the main information element as specified in the XSD structure
2. WHEN implementing signature THEN use **enveloped** signature type with required transforms as per government standards
3. WHEN choosing algorithms THEN use **RSA-SHA256** as default, with **SHA-1** as fallback if configured
4. WHEN including certificate THEN add only the holder's certificate in the appropriate X509 data structure
5. WHEN creating reference THEN use proper URI reference pointing to the signed element's Id attribute

### Requirement 6: XSD Validation System

**User Story:** As a developer, I want automated XSD validation to ensure generated XML is always compliant, so that integration errors are caught early.

#### Acceptance Criteria

1. WHEN implementing validation THEN use PHP `libxml` to validate against `DPS_v1.00.xsd`
2. WHEN validation fails THEN provide clear error messages indicating specific XSD violations
3. WHEN in manual emission interface THEN demonstrate XSD validation of generated XML
4. WHEN validation succeeds THEN confirm XML structure compliance before signing
5. WHEN integrating validation THEN include it in the manual emission routine (`html-admin-manual-emission.php`)

### Requirement 7: Payload Generation and Output

**User Story:** As an API consumer, I want the signed DPS XML available in multiple formats, so that I can integrate with different systems and transmission methods.

#### Acceptance Criteria

1. WHEN generating output THEN return signed XML as both string and Base64 formats
2. WHEN preparing for API transmission THEN ensure XML is properly escaped for JSON embedding
3. WHEN creating payload THEN make it ready for Produção Restrita and Production environments
4. WHEN outputting XML string THEN ensure proper encoding and character handling
5. WHEN encoding to Base64 THEN maintain XML integrity for transmission

### Requirement 8: Data Sanitization and Validation

**User Story:** As a compliance officer, I want all data to be properly sanitized and validated before XML generation, so that the DPS meets government data quality standards.

#### Acceptance Criteria

1. WHEN processing phone numbers THEN ensure only digits are included
2. WHEN processing email addresses THEN validate format and ensure plain text
3. WHEN processing service descriptions THEN strip all HTML tags and entities
4. WHEN processing monetary values THEN ensure proper decimal formatting (2 places for all values as per XSD types TSDec15V2 and TSDec1V2)
5. WHEN processing dates THEN ensure ISO8601 format for dhEmi and YYYY-MM-DD for dCompet
6. WHEN processing IBGE codes THEN ensure 7-digit municipal codes are used