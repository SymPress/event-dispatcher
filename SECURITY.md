# Security Policy

## Supported Versions

Security fixes are provided for the latest released version of this package.

## Reporting a Vulnerability

Please do not open public issues for suspected security vulnerabilities.

Report vulnerabilities by emailing `brian.schaeffner@sympress.de` with:

- A description of the issue and its impact
- Steps to reproduce or a minimal proof of concept
- Affected versions or commits, if known
- Any relevant logs with secrets removed

You should receive an acknowledgement within 72 hours. Confirmed vulnerabilities
will be handled with coordinated disclosure.

## Listener Safety

Events, listeners and subscribers are defined in trusted developer code and run
with the full privileges of the WordPress request. Hook event payloads, however,
often carry request or user data. Validate and sanitize payload values in
listeners before using them, for example before allowing additional upload MIME
types.
