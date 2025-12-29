const { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, 
        AlignmentType, BorderStyle, WidthType, ShadingType, VerticalAlign,
        Header, PageOrientation } = require('docx');
const fs = require('fs');

// Get student data from command line arguments
const studentData = JSON.parse(process.argv[2]);

// Border style
const thinBorder = { style: BorderStyle.SINGLE, size: 1, color: "000000" };
const cellBorders = { top: thinBorder, bottom: thinBorder, left: thinBorder, right: thinBorder };
const noBorder = { style: BorderStyle.NONE, size: 0, color: "FFFFFF" };
const noBorders = { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder };

// Helper function for table cells
function createCell(text, width, options = {}) {
    return new TableCell({
        borders: options.noBorder ? noBorders : cellBorders,
        width: { size: width, type: WidthType.DXA },
        shading: options.shading ? { fill: options.shading, type: ShadingType.CLEAR } : undefined,
        verticalAlign: VerticalAlign.CENTER,
        children: [new Paragraph({
            alignment: options.align || AlignmentType.LEFT,
            children: [new TextRun({ 
                text: text || '', 
                bold: options.bold || false,
                size: options.size || 20,
                font: "Arial"
            })]
        })]
    });
}

function createHeaderCell(text, width) {
    return createCell(text, width, { bold: true, shading: "E7E6E6", align: AlignmentType.CENTER });
}

// Assessment criteria rows
const assessmentItems = [
    { item: "Proposal", criteria: "Language", allocation: "10" },
    { item: "", criteria: "Content", allocation: "20" },
    { item: "Chapter 1", criteria: "Language", allocation: "10" },
    { item: "", criteria: "Content", allocation: "20" },
    { item: "Chapter 2", criteria: "Content", allocation: "20" },
    { item: "", criteria: "Language", allocation: "10" },
    { item: "Chapter 3", criteria: "Language", allocation: "10" },
    { item: "", criteria: "Content", allocation: "20" },
    { item: "Chapter 4", criteria: "Language", allocation: "10" },
    { item: "", criteria: "Content", allocation: "20" }
];

// Project requirements
const projectRequirements = [
    "Research Area and contribution. The project is within the area of specialization related to the programme of studies. The outcome of the project is able to contribute to the IT practices, target market, or knowledge.",
    "Information Technology content. The project is IT-related and has substantial amount of IT content.",
    "Technical Skill. The project requires the students to write substantial amounts of programming codes, or use of IT technical skills with the aid of tools.",
    "Methodology. The project allows the students to apply some kind of system development or research methodology.",
    "Practicality or Innovativeness. The project is an industrial project, or should practically represent a 'real-life' case of a company, or it is innovative and the idea is original."
];

// Create document
const doc = new Document({
    styles: {
        default: { document: { run: { font: "Arial", size: 20 } } }
    },
    sections: [{
        properties: {
            page: {
                margin: { top: 720, right: 720, bottom: 720, left: 720 },
                size: { orientation: PageOrientation.LANDSCAPE }
            }
        },
        children: [
            // Header with University name and Form title
            new Table({
                columnWidths: [7200, 7200],
                rows: [
                    new TableRow({
                        children: [
                            new TableCell({
                                borders: noBorders,
                                width: { size: 7200, type: WidthType.DXA },
                                children: [
                                    new Paragraph({
                                        children: [new TextRun({ text: "EXAMPLE UNIVERSITY", bold: true, size: 28, font: "Arial" })]
                                    }),
                                    new Paragraph({
                                        children: [new TextRun({ text: "Faculty of Computing and Information Technology", size: 20, font: "Arial" })]
                                    }),
                                    new Paragraph({
                                        spacing: { before: 100 },
                                        children: [new TextRun({ text: "BACS3403 Project 1", bold: true, size: 22, font: "Arial" })]
                                    })
                                ]
                            }),
                            new TableCell({
                                borders: noBorders,
                                width: { size: 7200, type: WidthType.DXA },
                                children: [
                                    new Paragraph({
                                        alignment: AlignmentType.RIGHT,
                                        children: [new TextRun({ text: "Form 3: Project Proposal", bold: true, size: 24, font: "Arial" })]
                                    }),
                                    new Paragraph({
                                        alignment: AlignmentType.RIGHT,
                                        children: [new TextRun({ text: "Moderation", bold: true, size: 24, font: "Arial" })]
                                    })
                                ]
                            })
                        ]
                    })
                ]
            }),
            
            new Paragraph({ spacing: { before: 200, after: 200 }, children: [] }),
            
            // Two-column layout using table
            new Table({
                columnWidths: [7200, 7200],
                rows: [
                    new TableRow({
                        children: [
                            // LEFT COLUMN - Project Details + Requirements
                            new TableCell({
                                borders: noBorders,
                                width: { size: 7200, type: WidthType.DXA },
                                children: [
                                    // Section 1: Project Details
                                    new Paragraph({
                                        shading: { fill: "D9D9D9", type: ShadingType.CLEAR },
                                        children: [new TextRun({ text: "1. Project Details", bold: true, size: 22, font: "Arial" })]
                                    }),
                                    new Table({
                                        columnWidths: [2000, 3500, 1200, 1500],
                                        rows: [
                                            new TableRow({ children: [
                                                createCell("Student Name", 2000, { bold: true }),
                                                createCell(studentData.student_name || "", 3500),
                                                createCell("Programme", 1200, { bold: true }),
                                                createCell(studentData.programme || "RSD2", 1500)
                                            ]}),
                                            new TableRow({ children: [
                                                createCell("Supervisor Name", 2000, { bold: true }),
                                                new TableCell({
                                                    borders: cellBorders,
                                                    width: { size: 6200, type: WidthType.DXA },
                                                    columnSpan: 3,
                                                    children: [new Paragraph({ children: [new TextRun({ text: studentData.supervisor || "", size: 20, font: "Arial" })] })]
                                                })
                                            ]}),
                                            new TableRow({ children: [
                                                createCell("Moderator Name", 2000, { bold: true }),
                                                new TableCell({
                                                    borders: cellBorders,
                                                    width: { size: 6200, type: WidthType.DXA },
                                                    columnSpan: 3,
                                                    children: [new Paragraph({ children: [new TextRun({ text: studentData.moderator || "", size: 20, font: "Arial" })] })]
                                                })
                                            ]}),
                                            new TableRow({ children: [
                                                createCell("Project Title/Scope", 2000, { bold: true }),
                                                new TableCell({
                                                    borders: cellBorders,
                                                    width: { size: 6200, type: WidthType.DXA },
                                                    columnSpan: 3,
                                                    children: [new Paragraph({ children: [new TextRun({ text: studentData.project || "", size: 20, font: "Arial" })] })]
                                                })
                                            ]}),
                                            new TableRow({ children: [
                                                createCell("Project Type", 2000, { bold: true }),
                                                createCell(studentData.project_type || "application", 3500),
                                                createCell("Project Category", 1200, { bold: true }),
                                                createCell(studentData.project_category || "Original Idea", 1500)
                                            ]})
                                        ]
                                    }),
                                    
                                    new Paragraph({ spacing: { before: 200 }, children: [] }),
                                    
                                    // Section 2: Project Scope Moderation
                                    new Paragraph({
                                        shading: { fill: "D9D9D9", type: ShadingType.CLEAR },
                                        children: [new TextRun({ text: "2. Project Scope Moderation [to be filled by Moderator] [Please tick (√) if comply]", bold: true, size: 20, font: "Arial" })]
                                    }),
                                    new Table({
                                        columnWidths: [6700, 500],
                                        rows: [
                                            new TableRow({ children: [
                                                createHeaderCell("Project Requirements", 6700),
                                                createHeaderCell("Comply", 500)
                                            ]}),
                                            ...projectRequirements.map(req => 
                                                new TableRow({ children: [
                                                    new TableCell({
                                                        borders: cellBorders,
                                                        width: { size: 6700, type: WidthType.DXA },
                                                        children: [new Paragraph({ children: [new TextRun({ text: req, size: 18, font: "Arial" })] })]
                                                    }),
                                                    createCell("", 500, { align: AlignmentType.CENTER })
                                                ]})
                                            )
                                        ]
                                    })
                                ]
                            }),
                            
                            // RIGHT COLUMN - Feedback + Assessment
                            new TableCell({
                                borders: noBorders,
                                width: { size: 7200, type: WidthType.DXA },
                                children: [
                                    // Section 3: Feedback
                                    new Paragraph({
                                        shading: { fill: "D9D9D9", type: ShadingType.CLEAR },
                                        children: [new TextRun({ text: "3. Feedback", bold: true, size: 22, font: "Arial" })]
                                    }),
                                    new Table({
                                        columnWidths: [4500, 2500],
                                        rows: [
                                            new TableRow({ children: [
                                                createHeaderCell("Comments and Changes Recommended (by moderator)", 4500),
                                                createHeaderCell("Actions Taken (by supervisor)", 2500)
                                            ]}),
                                            new TableRow({ 
                                                height: { value: 2500, rule: "exact" },
                                                children: [
                                                    new TableCell({
                                                        borders: cellBorders,
                                                        width: { size: 4500, type: WidthType.DXA },
                                                        children: [new Paragraph({ children: [] })]
                                                    }),
                                                    new TableCell({
                                                        borders: cellBorders,
                                                        width: { size: 2500, type: WidthType.DXA },
                                                        children: [new Paragraph({ children: [] })]
                                                    })
                                                ]
                                            })
                                        ]
                                    }),
                                    
                                    new Paragraph({ spacing: { before: 200 }, children: [] }),
                                    
                                    // Section 4: Assessment
                                    new Paragraph({
                                        shading: { fill: "D9D9D9", type: ShadingType.CLEAR },
                                        children: [new TextRun({ text: "4. Assessment", bold: true, size: 22, font: "Arial" })]
                                    }),
                                    new Table({
                                        columnWidths: [1500, 1500, 2000, 2000],
                                        rows: [
                                            new TableRow({ children: [
                                                createHeaderCell("Item", 1500),
                                                createHeaderCell("Criteria", 1500),
                                                createHeaderCell("Mark Allocation", 2000),
                                                createHeaderCell("Mark", 2000)
                                            ]}),
                                            ...assessmentItems.map(item => 
                                                new TableRow({ children: [
                                                    createCell(item.item, 1500),
                                                    createCell(item.criteria, 1500),
                                                    createCell(item.allocation, 2000, { align: AlignmentType.CENTER }),
                                                    createCell("", 2000, { align: AlignmentType.CENTER })
                                                ]})
                                            )
                                        ]
                                    }),
                                    
                                    new Paragraph({ spacing: { before: 300 }, children: [] }),
                                    
                                    // Signatures
                                    new Table({
                                        columnWidths: [3500, 3500],
                                        rows: [
                                            new TableRow({ children: [
                                                new TableCell({
                                                    borders: noBorders,
                                                    width: { size: 3500, type: WidthType.DXA },
                                                    children: [new Paragraph({ children: [new TextRun({ text: "Moderated by:", bold: true, size: 20, font: "Arial" })] })]
                                                }),
                                                new TableCell({
                                                    borders: noBorders,
                                                    width: { size: 3500, type: WidthType.DXA },
                                                    children: [new Paragraph({ children: [new TextRun({ text: "Received by:", bold: true, size: 20, font: "Arial" })] })]
                                                })
                                            ]}),
                                            new TableRow({ children: [
                                                new TableCell({
                                                    borders: noBorders,
                                                    width: { size: 3500, type: WidthType.DXA },
                                                    children: [
                                                        new Paragraph({ spacing: { before: 200 }, children: [] }),
                                                        new Paragraph({ children: [new TextRun({ text: "Moderator's Signature: ________________", size: 20, font: "Arial" })] })
                                                    ]
                                                }),
                                                new TableCell({
                                                    borders: noBorders,
                                                    width: { size: 3500, type: WidthType.DXA },
                                                    children: [
                                                        new Paragraph({ spacing: { before: 200 }, children: [] }),
                                                        new Paragraph({ children: [new TextRun({ text: "Supervisor's Signature: ________________", size: 20, font: "Arial" })] })
                                                    ]
                                                })
                                            ]}),
                                            new TableRow({ children: [
                                                new TableCell({
                                                    borders: noBorders,
                                                    width: { size: 3500, type: WidthType.DXA },
                                                    children: [
                                                        new Paragraph({ spacing: { before: 100 }, children: [] }),
                                                        new Paragraph({ children: [new TextRun({ text: "Moderation Date: ________________", size: 20, font: "Arial" })] })
                                                    ]
                                                }),
                                                new TableCell({
                                                    borders: noBorders,
                                                    width: { size: 3500, type: WidthType.DXA },
                                                    children: [
                                                        new Paragraph({ spacing: { before: 100 }, children: [] }),
                                                        new Paragraph({ children: [new TextRun({ text: "Received Date: ________________", size: 20, font: "Arial" })] })
                                                    ]
                                                })
                                            ]})
                                        ]
                                    })
                                ]
                            })
                        ]
                    })
                ]
            })
        ]
    }]
});

// Save document
const outputPath = process.argv[3] || '/tmp/Form3_Moderation.docx';
Packer.toBuffer(doc).then(buffer => {
    fs.writeFileSync(outputPath, buffer);
    console.log('Document created: ' + outputPath);
});