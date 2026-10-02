# Image upload handling

All web and API multipart image uploads pass through `OptimizeImageUploads` before controller validation and storage. This covers direct profile/category uploads as well as media-library files and manuscript supplementary figures.

- JPG/JPEG: jpegoptim lossless entropy optimization, retaining metadata; no quality setting, resizing or pixel re-encoding.
- PNG: OptiPNG lossless optimization, preserving dimensions and transparency.
- SVG: self-contained static vector content is validated and retained byte-for-byte. Scripts, CSS, animation, embedded images, external references and XML entities are rejected. Export plain SVG when necessary.
- WebP: accepted under the limit without re-encoding.
- Every accepted image is at most **5 MB (5,242,880 bytes)**. Files that cannot satisfy this limit losslessly produce a field validation error rather than an automatically degraded image.
- PDFs and Word documents remain unchanged and retain their existing document limits. Manuscripts and proofs still require their prescribed document formats; supplementary uploads also accept images.

Processing is bounded by the existing request limits, a 5 MB input-image ceiling, 40 megapixels, and a 15-second timeout per optimizer process. Optimizers run without shell interpolation, on upload-temporary files. Failed validation creates no stored media record.

Docker installs `jpegoptim` and `optipng`. On shared hosting without these executables or process execution, valid images within the size limit are stored unchanged. File type, SVG safety, image dimensions and size validation still apply. No external image processing API receives uploaded data. Existing stored images are not rewritten.

Authors can upload a JPG, PNG or WebP portrait in **Author studio → Profile**. Editors have the same option under **My profile** in their workspace and account menu. Both show the selected photo before saving and preserve the existing photo when no replacement is selected. Author portraits also update the linked public contributor profile.
