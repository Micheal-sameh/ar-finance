export interface ReportTitleProps {
    title: string;
    period: string;
}

/** The logo + report name + period header printed at the top of a report sheet. */
export function ReportTitle({ title, period }: ReportTitleProps) {
    return (
        <div className="report-title">
            <div>
                <h2>Avarewase Finance</h2>
                <p>{title}</p>
                <span>{period}</span>
            </div>
        </div>
    );
}
