<?php

// Client checklist: SJC.xlsx, Sheet1!A1:B50. Draft prompts are admin-only.
return json_decode(<<<'JSON'
{
  "home": {
    "number": 1,
    "title": "Home",
    "group": "journal",
    "route": "home",
    "legacy_key": null,
    "draft": ""
  },
  "about": {
    "number": 2,
    "title": "About the Journal",
    "group": "journal",
    "route": "about",
    "legacy_key": null,
    "draft": ""
  },
  "aims-scope": {
    "number": 3,
    "title": "Aims & Scope",
    "group": "journal",
    "route": null,
    "legacy_key": "policy.aims_scope",
    "draft": "## Purpose and coverage\n\nDefine the journal’s cardiology focus, intended readership, covered topics and work outside its scope.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "editorial-board": {
    "number": 4,
    "title": "Editorial Board",
    "group": "journal",
    "route": "editorial-board",
    "legacy_key": null,
    "draft": ""
  },
  "editorial-team": {
    "number": 5,
    "title": "Editorial Team / Editorial Structure",
    "group": "journal",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDescribe the responsibilities of the editor-in-chief, section editors, editorial office and independent advisers. Confirm names and appointments before publication.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "peer-review": {
    "number": 6,
    "title": "Peer Review Process",
    "group": "review",
    "route": null,
    "legacy_key": "policy.peer_review",
    "draft": "## Purpose and coverage\n\nState the review model, initial checks, reviewer selection, competing-interest checks, decisions, revisions and appeals. Confirm whether reviewer and author identities are concealed.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Reviewer guidance",
      "route": "policies.show",
      "parameters": {
        "page": "reviewer-guidelines"
      }
    }
  },
  "publication-ethics": {
    "number": 7,
    "title": "Publication Ethics & Malpractice Statement",
    "group": "ethics",
    "route": null,
    "legacy_key": "policy.publication_ethics",
    "draft": "## Purpose and coverage\n\nDefine responsibilities of authors, reviewers and editors; describe how ethical concerns are assessed and the publication record corrected. Do not claim membership or certification without evidence.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "plagiarism": {
    "number": 8,
    "title": "Plagiarism Policy",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nExplain originality requirements, quotation and attribution, similarity screening, human assessment and how authors may respond to concerns.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "research-misconduct": {
    "number": 9,
    "title": "Research Misconduct Policy",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDescribe reporting, confidential assessment, requests for evidence, institutional referrals, responses and possible editorial action.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Report a research concern",
      "route": "contact",
      "parameters": {
        "category": "ethics"
      }
    }
  },
  "authorship": {
    "number": 10,
    "title": "Authorship & Contributorship",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDefine authorship criteria, contribution statements, corresponding-author duties, author-list changes and how disputes are handled.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "competing-interests": {
    "number": 11,
    "title": "Conflict of Interest / Competing Interests",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nExplain which financial and non-financial interests authors, reviewers and editors must disclose, the time period covered and how conflicts are managed.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "ethical-oversight": {
    "number": 12,
    "title": "Research Ethics / Ethical Oversight",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDescribe ethics committee approval or exemption, vulnerable groups, confidentiality and documentation required for research submissions.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "informed-consent": {
    "number": 13,
    "title": "Informed Consent Policy",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nSpecify consent for participation and separate consent for publication of identifiable information. Explain anonymisation and how consent documentation is checked securely.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "human-animal-research": {
    "number": 14,
    "title": "Human & Animal Research Ethics",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nState the required ethics approval, consent, animal welfare oversight and reporting standards applicable to human and animal studies.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "data-sharing": {
    "number": 15,
    "title": "Data Sharing & Reproducibility Policy",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDefine the data availability statement, repositories, access conditions, code sharing and justified restrictions for privacy or consent.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "copyright": {
    "number": 16,
    "title": "Intellectual Property / Copyright Policy",
    "group": "policies",
    "route": null,
    "legacy_key": "policy.copyright",
    "draft": "## Purpose and coverage\n\nSpecify who retains copyright, what publication rights are granted and how third-party material and permissions are handled.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "licensing": {
    "number": 17,
    "title": "Licensing Policy",
    "group": "policies",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nConfirm the exact licence and version, permitted reuse, attribution obligations and exceptions for third-party content.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "corrections-retractions": {
    "number": 18,
    "title": "Corrections & Retractions",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nExplain when corrections, expressions of concern or retractions are issued, who decides, and how notices remain linked to the affected article.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Browse publication notices",
      "route": "corrections.index",
      "parameters": {}
    }
  },
  "complaints-appeals": {
    "number": 19,
    "title": "Complaints & Appeals",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDescribe the complaints channel, information required, independent escalation, response process and appeal grounds. Confirm any promised response times.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Submit a complaint or appeal",
      "route": "contact",
      "parameters": {
        "category": "complaints"
      }
    }
  },
  "post-publication-discussion": {
    "number": 20,
    "title": "Post-Publication Discussions / Comments",
    "group": "review",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nExplain how readers submit comments, editorial moderation, competing-interest disclosure and handling of substantive concerns.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Browse articles and comments",
      "route": "articles.index",
      "parameters": {}
    }
  },
  "generative-ai": {
    "number": 21,
    "title": "Generative AI / AI Policy",
    "group": "ethics",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDefine disclosure of AI use, human responsibility, prohibited uses, confidentiality and rules for images, data and peer review.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "open-access": {
    "number": 22,
    "title": "Open Access Policy",
    "group": "policies",
    "route": null,
    "legacy_key": "policy.open_access",
    "draft": "## Purpose and coverage\n\nConfirm what content is openly available, when access begins and which licence governs reuse. Distinguish access from copyright permissions.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "fees": {
    "number": 23,
    "title": "Article Processing Charges (APC)",
    "group": "authors",
    "route": null,
    "legacy_key": "policy.fees",
    "draft": "## Purpose and coverage\n\nProvide approved APC amounts, currency, taxes, article types, when payment is due, refunds and exceptions. Do not assume that no listed charge means free publication.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Ask about charges",
      "route": "contact",
      "parameters": {
        "category": "fees"
      }
    }
  },
  "waivers": {
    "number": 24,
    "title": "Waiver / Discount Policy",
    "group": "authors",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nSpecify eligibility, application timing, supporting information, decision process and whether editorial decisions are independent of payment.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Enquire about a waiver",
      "route": "contact",
      "parameters": {
        "category": "fees"
      }
    }
  },
  "archiving": {
    "number": 25,
    "title": "Archiving Policy",
    "group": "policies",
    "route": null,
    "legacy_key": "policy.archiving",
    "draft": "## Purpose and coverage\n\nDescribe author self-archiving permissions, allowed manuscript versions, repositories, embargoes and required links.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "digital-preservation": {
    "number": 26,
    "title": "Digital Preservation Policy",
    "group": "policies",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nIdentify the actual preservation provider, covered content, access arrangements and evidence of participation. Server backups alone should not be described as an external preservation programme.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "publication-frequency": {
    "number": 27,
    "title": "Publication Frequency",
    "group": "journal",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nConfirm the publication schedule, issue frequency and any continuous-publication arrangements.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "journal-history": {
    "number": 28,
    "title": "Journal History",
    "group": "journal",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nSupply verified founding dates, previous titles, publisher changes and significant milestones.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "issn": {
    "number": 29,
    "title": "ISSN / eISSN Information",
    "group": "journal",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nSupply the assigned print ISSN and electronic ISSN with registry evidence. Leave unavailable identifiers blank.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "indexing": {
    "number": 30,
    "title": "Indexing & Abstracting",
    "group": "journal",
    "route": null,
    "legacy_key": "policy.indexing",
    "draft": "## Purpose and coverage\n\nList only confirmed indexing services with official verification links and dates of coverage.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Database coverage",
      "route": "policies.show",
      "parameters": {
        "page": "database-coverage"
      }
    }
  },
  "database-coverage": {
    "number": 31,
    "title": "Abstracting / Database Coverage",
    "group": "journal",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDescribe confirmed database coverage, included years and article types, with links to the database’s own journal record.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "author-guidelines": {
    "number": 32,
    "title": "Author Guidelines / Instructions for Authors",
    "group": "authors",
    "route": null,
    "legacy_key": "policy.author_guidelines",
    "draft": "## Purpose and coverage\n\nExplain eligibility, article types, declarations, files, manuscript structure, references, submission and revision requirements.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Start a submission",
      "route": "author.dashboard",
      "parameters": {}
    }
  },
  "manuscript-submission": {
    "number": 33,
    "title": "Manuscript Submission",
    "group": "authors",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nExplain how to create an author account, prepare metadata, upload manuscript and supplementary files, complete declarations and track progress.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Open author workspace",
      "route": "author.dashboard",
      "parameters": {}
    }
  },
  "article-types": {
    "number": 34,
    "title": "Article Types",
    "group": "authors",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nList accepted article types with approved word limits, abstract requirements, figure limits and reference limits.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "manuscript-preparation": {
    "number": 35,
    "title": "Manuscript Preparation Guidelines",
    "group": "authors",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nSpecify title page, abstract, main text, tables, figures, supplementary files, anonymisation and file-format requirements.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "reference-style": {
    "number": 36,
    "title": "Reference / Citation Style",
    "group": "authors",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nSpecify the approved citation system with examples for journals, books, datasets, websites and preprints.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "publication-charges": {
    "number": 37,
    "title": "Publication Charges",
    "group": "authors",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nList all approved submission, publication, colour, page or supplementary charges, taxes and payment terms; link to APC and waiver information.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Ask about charges",
      "route": "contact",
      "parameters": {
        "category": "fees"
      }
    }
  },
  "reviewer-guidelines": {
    "number": 38,
    "title": "Reviewers / Reviewer Guidelines",
    "group": "review",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nExplain accepting or declining invitations, deadlines, confidentiality, evaluating methods, constructive feedback and submitting recommendations.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Open reviewer workspace",
      "route": "reviewer.dashboard",
      "parameters": {}
    }
  },
  "reviewer-conduct": {
    "number": 39,
    "title": "Reviewer Code of Conduct",
    "group": "review",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nSet expectations for confidentiality, competing interests, respectful feedback, evidence-based critique and permitted use of tools or assistance.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Open reviewer workspace",
      "route": "reviewer.dashboard",
      "parameters": {}
    }
  },
  "editorial-policies": {
    "number": 40,
    "title": "Editorial Policies",
    "group": "review",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDefine editorial independence, decision authority, handling editor conflicts, special issues and the separation of commercial and editorial decisions.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "publisher": {
    "number": 41,
    "title": "Publisher Information",
    "group": "journal",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nProvide the verified publisher’s legal name, address, ownership and contact information.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "contact": {
    "number": 42,
    "title": "Contact Us",
    "group": "journal",
    "route": "contact",
    "legacy_key": null,
    "draft": ""
  },
  "privacy": {
    "number": 43,
    "title": "Privacy Policy",
    "group": "policies",
    "route": null,
    "legacy_key": "policy.privacy",
    "draft": "## Purpose and coverage\n\nDescribe collected information, purposes, retention, processors, international transfers, user rights and the privacy contact. Confirm applicable law and actual hosting and email services.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval.",
    "action": {
      "label": "Contact the journal",
      "route": "contact",
      "parameters": {
        "category": "privacy"
      }
    }
  },
  "terms": {
    "number": 44,
    "title": "Terms & Conditions",
    "group": "policies",
    "route": null,
    "legacy_key": "policy.terms",
    "draft": "## Purpose and coverage\n\nSpecify the terms for accessing the site, accounts, permitted use, submissions, restrictions, governing law and dispute process.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "copyright-notice": {
    "number": 45,
    "title": "Copyright Notice",
    "group": "policies",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nProvide the approved website copyright notice and distinguish website assets from individual article rights.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "disclaimer": {
    "number": 46,
    "title": "Site Disclaimer",
    "group": "policies",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nClarify the educational purpose of published content, responsibility for opinions and limits of reliance, subject to applicable law.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "advertising": {
    "number": 47,
    "title": "Advertising Policy",
    "group": "policies",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nDefine accepted advertising, prohibited categories, labelling, approval, complaints and separation from editorial decisions.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "sponsorship": {
    "number": 48,
    "title": "Sponsorship Policy",
    "group": "policies",
    "route": null,
    "legacy_key": null,
    "draft": "## Purpose and coverage\n\nExplain sponsor disclosure, funding independence, sponsored material labelling and conflict management.\n\n## Procedure and responsibilities\n\nAdd the approved steps, responsible roles and any documented exceptions.\n\n## Questions and review\n\nSpecify the journal contact and review date after approval."
  },
  "corrections-archive": {
    "number": 49,
    "title": "Corrections / Retractions Archive",
    "group": "records",
    "route": "corrections.index",
    "legacy_key": null,
    "draft": ""
  },
  "current-issue": {
    "number": 50,
    "title": "Current Issue / Archives",
    "group": "records",
    "route": "current-issue",
    "legacy_key": null,
    "draft": ""
  }
}
JSON, true, 512, JSON_THROW_ON_ERROR);
