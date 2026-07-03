import type { ReactNode } from 'react';

interface AuthCardProps {
  title: string;
  children: ReactNode;
}

export default function AuthCard({ title, children }: AuthCardProps) {
  return (
    <div className="container">
      <div className="row justify-content-center">
        <div className="col-12 col-sm-10 col-md-7 col-lg-5">
          <div className="card shadow-sm my-5">
            <div className="card-body p-4">
              <h1 className="h4 mb-4 text-center">{title}</h1>
              {children}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
