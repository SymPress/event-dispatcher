# Release 1.0.5

The archive caller now grants the job-level permissions required by the reusable artifact-attestation workflow. This fixes the pre-existing tag workflow startup failure. Development and test files are excluded from the installable archive, and the source plugin header reflects the release version.

The dispatcher behavior remains the 1.0.4 implementation: the actual kernel debug setting is applied before compiled listeners are loaded.
